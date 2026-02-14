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
            'core.common'                => 'handle_email_search',
            'core.adm_page_header_after' => 'inject_email_field',
        ];
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

        $sql = 'SELECT user_id FROM ' . USERS_TABLE . "
                WHERE user_email = '" . $this->db->sql_escape($email) . "'";
        $result = $this->db->sql_query($sql);
        $user_id = (int) $this->db->sql_fetchfield('user_id');
        $this->db->sql_freeresult($result);

        if ($user_id)
        {
            $this->request->overwrite('u', $user_id, \phpbb\request\request_interface::REQUEST);
        }
        else
        {
            $this->template->assign_vars([
                'S_EMAIL_NOT_FOUND' => true,
                'EMAIL_SEARCHED'    => $email,
            ]);
        }
    }

    /**
     * Inject email search field into the ACP "Manage Users" form via JavaScript.
     * The S_SELECT_USER block has no template event, so we inject via JS.
     */
    public function inject_email_field()
    {
        $this->template->assign_var('S_ADMINHELPER_INJECT_EMAIL', true);

        $email_searched = $this->request->variable('email_search', '', true);
        if ($email_searched)
        {
            $this->template->assign_var('ADMINHELPER_EMAIL_VALUE', $email_searched);
        }
    }
}
