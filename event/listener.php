<?php
namespace bastien59960\adminhelper\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    protected $db;
    protected $request;
    protected $template;
    protected $language;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language
    ) {
        $this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.common'           => 'handle_email_search',
            'core.adm_page_header'  => 'load_language',
        ];
    }

    /**
     * Load language file for ACP template events.
     */
    public function load_language()
    {
        $this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');
    }

    /**
     * Intercept email search on ACP "Manage Users" page.
     * Fires before acp_users.php reads its variables, so we can
     * overwrite the 'u' (user_id) request variable with the result.
     */
    public function handle_email_search()
    {
        $email = $this->request->variable('email_search', '', true);
        if (!$email)
        {
            return;
        }

        $this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');

        $sql = 'SELECT user_id FROM ' . USERS_TABLE . "
                WHERE user_email = '" . $this->db->sql_escape($email) . "'";
        $result = $this->db->sql_query($sql);
        $user_id = (int) $this->db->sql_fetchfield('user_id');
        $this->db->sql_freeresult($result);

        if ($user_id)
        {
            // Inject user_id into the request so acp_users.php finds it
            $this->request->overwrite('u', $user_id, \phpbb\request\request_interface::REQUEST);
        }
        else
        {
            // Email not found — set template variable for error display
            $this->template->assign_vars([
                'S_EMAIL_NOT_FOUND' => true,
                'EMAIL_SEARCHED'    => $email,
            ]);
        }
    }
}
