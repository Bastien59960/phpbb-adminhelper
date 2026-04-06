<?php
namespace bastien59960\adminhelper\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\request\request_interface;
use bastien59960\adminhelper\service\attachment_ai_manager;

class listener implements EventSubscriberInterface
{
    protected $db;
    protected $request;
    protected $template;
    protected $language;
    protected $auth;
    protected $user;
    protected $config;
    protected $attachment_ai_manager;
    protected $mod_notes_table;
    protected $language_loaded;
    protected $search_is_author;
    protected $search_post_attaches;
    protected $mass_email_context;
    protected $mass_email_append_unsubscribe;
    protected $mass_email_one_click_enabled;
    protected $mass_email_html_enabled;
    protected $mass_email_html_body;
    protected $mass_email_boundary;
    protected $current_unsubscribe_one_click_url;
    protected $current_unsubscribe_type;
    protected $notification_email_context;
    protected $recipient_user_cache;
    protected $ucp_notify_unsub_logged;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language,
        \phpbb\auth\auth $auth,
        \phpbb\user $user,
        ?\phpbb\config\config $config = null,
        attachment_ai_manager $attachment_ai_manager,
        string $table_prefix = 'phpbb3_'
    ) {
        $this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
        $this->auth = $auth;
        $this->user = $user;
        $this->config = $config;
        $this->attachment_ai_manager = $attachment_ai_manager;
        $this->mod_notes_table = $table_prefix . 'adminhelper_mod_notes';
        $this->language_loaded = false;
        $this->search_is_author = false;
        $this->search_post_attaches = [];
        $this->mass_email_context = false;
        $this->mass_email_append_unsubscribe = false;
        $this->mass_email_one_click_enabled = false;
        $this->mass_email_html_enabled = false;
        $this->mass_email_html_body = '';
        $this->mass_email_boundary = '';
        $this->current_unsubscribe_one_click_url = '';
        $this->current_unsubscribe_type = '';
        $this->notification_email_context = false;
        $this->recipient_user_cache = [];
        $this->ucp_notify_unsub_logged = false;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.common'                      => 'handle_email_search',
            'core.adm_page_header_after'       => 'inject_email_field',
            'core.acp_email_modify_sql'        => 'acp_email_modify_sql',
            'core.acp_email_display'           => 'acp_email_display',
            'core.acp_email_send_before'       => 'acp_email_send_before',
            'core.modify_notification_message' => 'modify_notification_message',
            'core.notification_message_email'  => 'notification_message_email',
            'core.notification_message_process' => 'notification_message_process',
            'core.modify_email_headers'        => 'modify_email_headers',
            'core.posting_modify_template_vars' => 'inject_attachment_ai_posting_vars',
            'core.modify_attachment_data_on_upload' => 'handle_attachment_ai_upload_data',
            'core.modify_attachment_data_on_submit' => 'handle_attachment_ai_upload_data',
            'core.submit_post_end' => 'sync_attachment_ai_submission',
            'core.ucp_prefs_personal_update_data' => 'ucp_prefs_personal_update_data',
            'core.ucp_prefs_post_update_data'  => 'ucp_prefs_post_update_data',
            'core.ucp_notifications_submit_notification_is_set' => 'ucp_notifications_submit_notification_is_set',
            'core.search_modify_rowset'                        => 'on_search_author_rowset',
            'core.search_modify_tpl_ary'                       => 'on_search_author_tpl_ary',
            'core.page_header'                                 => 'on_page_header',
            'core.viewtopic_post_row_after'                    => 'on_viewtopic_post_row',
            'core.parse_attachments_modify_template_data'      => 'on_parse_attachment_ai_warning',
            'core.submit_post_before'                          => 'normalize_post_urls',
        ];
    }

    public function inject_attachment_ai_posting_vars($event)
    {
        $forum_id = (int) ($event['forum_id'] ?? 0);
        if ($forum_id <= 0) {
            return;
        }

        $this->load_language();

        $page_data = $event['page_data'];
        $post_id = (int) ($event['post_id'] ?? 0);

        $requested_checked_ids = $this->attachment_ai_manager->parse_attachment_id_csv(
            $this->request->variable('geoexplo_ai_generated_attachment_ids', '', true)
        );
        $requested_forced_ids = $this->attachment_ai_manager->parse_attachment_id_csv(
            $this->request->variable('geoexplo_ai_forced_attachment_ids', '', true)
        );
        $attachment_ids = $this->get_requested_attachment_ids();
        if (empty($attachment_ids) && $post_id > 0) {
            $attachment_ids = $this->attachment_ai_manager->get_post_image_attachment_ids($post_id);
        }

        $posting_state = $this->attachment_ai_manager->get_posting_state(
            $attachment_ids,
            $requested_checked_ids,
            $requested_forced_ids
        );

        $page_data['S_ADMINHELPER_ATTACHMENT_AI_POSTING'] = true;
        $page_data['ADMINHELPER_AI_ATTACHMENT_IDS'] = implode(',', $posting_state['checked_ids']);
        $page_data['ADMINHELPER_AI_FORCED_ATTACHMENT_IDS'] = implode(',', $posting_state['forced_ids']);
        $page_data['ADMINHELPER_AI_ATTACHMENT_STATE_B64'] = !empty($posting_state['states'])
            ? base64_encode((string) json_encode($posting_state['states']))
            : '';

        $event['page_data'] = $page_data;
    }

    public function handle_attachment_ai_upload_data($event)
    {
        $attachment_data = $event['attachment_data'];
        if (!is_array($attachment_data) || empty($attachment_data)) {
            return;
        }

        $new_entry = reset($attachment_data);
        $attach_id = (int) (($new_entry['attach_id'] ?? 0));
        if ($attach_id <= 0) {
            return;
        }

        $state = $this->attachment_ai_manager->record_attachment_scan_by_id($attach_id);
        if (empty($state)) {
            return;
        }

        foreach ($attachment_data as &$entry) {
            if ((int) ($entry['attach_id'] ?? 0) !== $attach_id) {
                continue;
            }

            $entry['adminhelper_ai_generated'] = !empty($state['is_ai_generated']) ? 1 : 0;
            $entry['adminhelper_ai_forced'] = !empty($state['is_forced']) ? 1 : 0;
            $entry['adminhelper_ai_provider'] = (string) ($state['ai_provider'] ?? '');
            $entry['adminhelper_ai_source'] = (string) ($state['detection_source'] ?? '');
            $entry['adminhelper_ai_reason'] = (string) ($state['detection_reason'] ?? '');
            $entry['adminhelper_ai_scan_status'] = (string) ($state['scan_status'] ?? '');
            break;
        }
        unset($entry);

        $event['attachment_data'] = $attachment_data;
    }

    public function sync_attachment_ai_submission($event)
    {
        $data = $event['data'];
        if (!is_array($data)) {
            return;
        }

        $post_id = (int) ($data['post_id'] ?? 0);
        $user_id = (int) ($data['poster_id'] ?? ($this->user->data['user_id'] ?? 0));
        if ($post_id <= 0) {
            return;
        }

        $checked_ids = $this->attachment_ai_manager->parse_attachment_id_csv(
            $this->request->variable('geoexplo_ai_generated_attachment_ids', '', true)
        );
        $this->attachment_ai_manager->sync_post_flags($post_id, $user_id, $checked_ids);
    }

    public function on_parse_attachment_ai_warning($event)
    {
        $attachment = (array) ($event['attachment'] ?? []);
        if (empty($attachment) || !$this->attachment_ai_manager->is_image_attachment($attachment)) {
            return;
        }

        $attach_id = (int) ($attachment['attach_id'] ?? 0);
        if ($attach_id <= 0) {
            return;
        }

        $state = $this->attachment_ai_manager->get_attachment_state_by_id($attach_id, true);
        if (empty($state['is_ai_generated'])) {
            return;
        }

        $this->load_language();

        $block_array = $event['block_array'];
        $block_array['S_ADMINHELPER_AI_WARNING'] = true;
        $provider_label = $this->attachment_ai_provider_label((string) ($state['ai_provider'] ?? ''));
        if ($provider_label !== '') {
            $block_array['ADMINHELPER_AI_PROVIDER_NOTICE'] = $this->language->lang(
                'ADMINHELPER_AI_ATTACHMENT_DETECTED_SOURCE',
                $provider_label
            );
        }
        $event['block_array'] = $block_array;
    }

    private function attachment_ai_provider_label($provider)
    {
        $key = 'ADMINHELPER_AI_PROVIDER_' . strtoupper((string) $provider);
        if (isset($this->user->lang[$key])) {
            return $this->language->lang($key);
        }

        return '';
    }

    /**
     * Track manual unsubscribe actions performed from UCP personal preferences.
     */
    public function ucp_prefs_personal_update_data($event)
    {
        global $user;

        if (!isset($user->data['user_id']) || (int) $user->data['user_id'] <= ANONYMOUS)
        {
            return;
        }

        $sql_ary = isset($event['sql_ary']) && is_array($event['sql_ary']) ? $event['sql_ary'] : [];
        $user_id = (int) $user->data['user_id'];
        $user_email = (string) ($user->data['user_email'] ?? '');

        $old_massmail = (int) ($user->data['user_allow_massemail'] ?? 0);
        $new_massmail = isset($sql_ary['user_allow_massemail']) ? (int) $sql_ary['user_allow_massemail'] : $old_massmail;
        if ($old_massmail === 1 && $new_massmail === 0)
        {
            $this->log_unsubscribe_event('manual_unsubscribed', 200, [
                'user_id' => $user_id,
                'user_email' => $user_email,
                'unsubscribe_type' => 'massmail',
                'token_expires_at' => 0,
            ]);
        }

        $old_notify_enabled = (int) ($user->data['user_notify'] ?? 0) === 1;
        $old_notify_type = (int) ($user->data['user_notify_type'] ?? NOTIFY_EMAIL);
        $new_notify_type = isset($sql_ary['user_notify_type']) ? (int) $sql_ary['user_notify_type'] : $old_notify_type;
        $old_email_enabled = in_array($old_notify_type, [NOTIFY_EMAIL, NOTIFY_BOTH], true);
        $new_email_enabled = in_array($new_notify_type, [NOTIFY_EMAIL, NOTIFY_BOTH], true);

        if ($old_notify_enabled && $old_email_enabled && !$new_email_enabled)
        {
            $this->log_unsubscribe_event('manual_unsubscribed', 200, [
                'user_id' => $user_id,
                'user_email' => $user_email,
                'unsubscribe_type' => 'forum_notify',
                'token_expires_at' => 0,
            ]);
        }
    }

    /**
     * Track manual unsubscribe actions from UCP posting preferences.
     */
    public function ucp_prefs_post_update_data($event)
    {
        global $user;

        if (!isset($user->data['user_id']) || (int) $user->data['user_id'] <= ANONYMOUS)
        {
            return;
        }

        $sql_ary = isset($event['sql_ary']) && is_array($event['sql_ary']) ? $event['sql_ary'] : [];
        if (!isset($sql_ary['user_notify']))
        {
            return;
        }

        $old_notify = (int) ($user->data['user_notify'] ?? 0);
        $new_notify = (int) $sql_ary['user_notify'];
        $old_notify_type = (int) ($user->data['user_notify_type'] ?? NOTIFY_EMAIL);
        $old_email_enabled = in_array($old_notify_type, [NOTIFY_EMAIL, NOTIFY_BOTH], true);
        if (!($old_notify === 1 && $new_notify === 0 && $old_email_enabled))
        {
            return;
        }

        $this->log_unsubscribe_event('manual_unsubscribed', 200, [
            'user_id' => (int) $user->data['user_id'],
            'user_email' => (string) ($user->data['user_email'] ?? ''),
            'unsubscribe_type' => 'forum_notify',
            'token_expires_at' => 0,
        ]);
    }

    /**
     * Log when a user disables email notifications via the UCP notification options page.
     * Fires once per notification method entry — we only care about the email method
     * and only log once per form submission (flag prevents duplicate rows).
     */
    public function ucp_notifications_submit_notification_is_set($event)
    {
        if ($this->ucp_notify_unsub_logged)
        {
            return;
        }

        global $user;

        if (!isset($user->data['user_id']) || (int) $user->data['user_id'] <= ANONYMOUS)
        {
            return;
        }

        $method_data  = isset($event['method_data'])  && is_array($event['method_data'])  ? $event['method_data']  : [];
        $is_set_notify = isset($event['is_set_notify']) ? (bool) $event['is_set_notify'] : true;

        if (($method_data['id'] ?? '') !== 'notification.method.email')
        {
            return;
        }

        if ($is_set_notify)
        {
            return;
        }

        $this->ucp_notify_unsub_logged = true;

        $this->log_unsubscribe_event('ucp_notify_options_updated', 200, [
            'user_id'          => (int) $user->data['user_id'],
            'user_email'       => (string) ($user->data['user_email'] ?? ''),
            'unsubscribe_type' => 'forum_notify',
            'token_expires_at' => 0,
        ]);
    }

    /**
     * Intercept email search on ACP "Manage Users" page.
     * Fires before acp_users.php reads its variables, so we can
     * overwrite the 'u' (user_id) request variable with the result.
     */
    public function handle_email_search($event = null)
    {
        $this->handle_unsubscribe_request();
        $this->prepare_mass_email_message_fallback();

        if ($this->request->variable('i', '') !== 'acp_users')
        {
            return;
        }

        $email = trim((string) $this->request->variable('email_search', '', true));
        if ($email === '')
        {
            return;
        }

        $user_id = $this->find_user_id_by_email($email);

        if ($user_id)
        {
            $this->request->overwrite('u', $user_id, \phpbb\request\request_interface::REQUEST);
            $this->request->overwrite('u', $user_id, \phpbb\request\request_interface::POST);
            $this->request->overwrite('u', $user_id, \phpbb\request\request_interface::GET);
        }
        else
        {
            $this->load_language();
            $this->template->assign_vars([
                'S_EMAIL_NOT_FOUND'                  => true,
                'EMAIL_SEARCHED'                     => $email,
                'ADMINHELPER_EMAIL_NOT_FOUND_MESSAGE' => addslashes($this->language->lang('ADMINHELPER_EMAIL_NOT_FOUND', $email)),
            ]);
        }
    }

    /**
     * Inject email search field into the ACP "Manage Users" form via JavaScript.
     * The S_SELECT_USER block has no template event, so we inject via JS.
     */
    public function inject_email_field($event = null)
    {
        $this->load_language();
        $this->template->assign_vars([
            'S_ADMINHELPER_INJECT_EMAIL'    => true,
            'ADMINHELPER_SEARCH_BY_EMAIL'   => addslashes($this->language->lang('ADMINHELPER_SEARCH_BY_EMAIL')),
            'ADMINHELPER_EMAIL_PLACEHOLDER' => addslashes($this->language->lang('ADMINHELPER_EMAIL_PLACEHOLDER')),
        ]);

        $email_searched = $this->request->variable('email_search', '', true);
        if ($email_searched)
        {
            $this->template->assign_var('ADMINHELPER_EMAIL_VALUE', addslashes($email_searched));
        }
    }

    /**
     * Adjust ACP email recipient selection before query execution.
     * RFC 8058 one-click requires recipient-specific unsubscribe URLs,
     * so we force one recipient per message when enabled.
     */
    public function acp_email_modify_sql($event)
    {
        global $config;

        if (!$this->is_acp_mass_email_submit())
        {
            return;
        }

        if (!(bool) $this->request->variable('adminhelper_enable_one_click', 1))
        {
            return;
        }

        $config['email_max_chunk_size'] = 1;
    }

    /**
     * Add HTML message fields on ACP mass email form.
     */
    public function acp_email_display($event)
    {
        $this->load_language();

        $template_data = $event['template_data'];
        $template_data['ADMINHELPER_HTML_MESSAGE_VALUE'] = $this->request->variable('adminhelper_message_html', '', true);
        $template_data['S_ADMINHELPER_USE_HTML_EMAIL'] = (bool) $this->request->variable('adminhelper_use_html', 0);
        $template_data['S_ADMINHELPER_APPEND_UNSUBSCRIBE'] = (bool) $this->request->variable('adminhelper_append_unsubscribe', 1);
        $template_data['S_ADMINHELPER_ENABLE_ONE_CLICK'] = (bool) $this->request->variable('adminhelper_enable_one_click', 1);
        $template_data['ADMINHELPER_HTML_MESSAGE_LABEL'] = $this->language->lang('ADMINHELPER_HTML_MESSAGE_LABEL');
        $template_data['ADMINHELPER_HTML_MESSAGE_EXPLAIN'] = $this->language->lang('ADMINHELPER_HTML_MESSAGE_EXPLAIN');
        $template_data['ADMINHELPER_USE_HTML_LABEL'] = $this->language->lang('ADMINHELPER_USE_HTML_LABEL');
        $template_data['ADMINHELPER_USE_HTML_EXPLAIN'] = $this->language->lang('ADMINHELPER_USE_HTML_EXPLAIN');
        $template_data['ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL'] = $this->language->lang('ADMINHELPER_APPEND_UNSUBSCRIBE_LABEL');
        $template_data['ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN'] = $this->language->lang('ADMINHELPER_APPEND_UNSUBSCRIBE_EXPLAIN');
        $template_data['ADMINHELPER_ENABLE_ONE_CLICK_LABEL'] = $this->language->lang('ADMINHELPER_ENABLE_ONE_CLICK_LABEL');
        $template_data['ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN'] = $this->language->lang('ADMINHELPER_ENABLE_ONE_CLICK_EXPLAIN');
        $event['template_data'] = $template_data;
    }

    /**
     * Activate HTML multipart mode for ACP mass email.
     */
    public function acp_email_send_before($event)
    {
        $this->mass_email_context = false;
        $this->mass_email_append_unsubscribe = false;
        $this->mass_email_one_click_enabled = false;
        $this->mass_email_html_enabled = false;
        $this->mass_email_html_body = '';
        $this->mass_email_boundary = '';
        $this->current_unsubscribe_one_click_url = '';
        $this->current_unsubscribe_type = '';
        $this->notification_email_context = false;
        $this->recipient_user_cache = [];

        if ($event['email_template'] !== 'admin_send_email')
        {
            return;
        }

        $this->load_language();
        $template_data = $event['template_data'];
        $use_html = (bool) $this->request->variable('adminhelper_use_html', 0);
        $append_unsubscribe = (bool) $this->request->variable('adminhelper_append_unsubscribe', 1);
        $enable_one_click = (bool) $this->request->variable('adminhelper_enable_one_click', 1);

        $this->mass_email_context = true;
        $this->mass_email_append_unsubscribe = $append_unsubscribe;
        $this->mass_email_one_click_enabled = $enable_one_click;

        if ($enable_one_click)
        {
            // One-click requires recipient-specific links; always queue to avoid long blocking SMTP requests.
            $event['use_queue'] = true;
            @set_time_limit(0);
        }

        $unsubscribe_url = '';
        $unsubscribe_text_footer = '';
        $unsubscribe_html_footer = '';
        if ($append_unsubscribe)
        {
            $unsubscribe_url = $this->build_profile_preferences_url();
            $unsubscribe_text_footer = $this->language->lang('ADMINHELPER_UNSUBSCRIBE_TEXT', $unsubscribe_url);
            $unsubscribe_html_footer = $this->language->lang(
                'ADMINHELPER_UNSUBSCRIBE_HTML',
                htmlspecialchars($unsubscribe_url, ENT_QUOTES, 'UTF-8')
            );
        }

        if (!$use_html)
        {
            if ($append_unsubscribe)
            {
                $current_message = isset($template_data['MESSAGE'])
                    ? (string) $template_data['MESSAGE']
                    : (string) $this->request->variable('message', '', true);
                $template_data['MESSAGE'] = $this->append_text_footer($current_message, $unsubscribe_text_footer, $unsubscribe_url);
                $event['template_data'] = $template_data;
            }
            return;
        }

        $html_message = $this->request->variable('adminhelper_message_html', '', true);
        if ($html_message === '')
        {
            if ($append_unsubscribe)
            {
                $current_message = isset($template_data['MESSAGE'])
                    ? (string) $template_data['MESSAGE']
                    : (string) $this->request->variable('message', '', true);
                $template_data['MESSAGE'] = $this->append_text_footer($current_message, $unsubscribe_text_footer, $unsubscribe_url);
                $event['template_data'] = $template_data;
            }
            return;
        }

        $html_message = html_entity_decode($html_message, ENT_COMPAT, 'UTF-8');
        $html_message = $this->sanitize_html_email($html_message);
        if ($html_message === '')
        {
            if ($append_unsubscribe)
            {
                $current_message = isset($template_data['MESSAGE'])
                    ? (string) $template_data['MESSAGE']
                    : (string) $this->request->variable('message', '', true);
                $template_data['MESSAGE'] = $this->append_text_footer($current_message, $unsubscribe_text_footer, $unsubscribe_url);
                $event['template_data'] = $template_data;
            }
            return;
        }

        $plain_text_message = trim((string) $this->request->variable('message', '', true));
        if ($plain_text_message === '')
        {
            $text_fallback = $this->html_to_text($html_message);
            if ($text_fallback !== '')
            {
                $template_data['MESSAGE'] = $text_fallback;
            }
        }

        if ($append_unsubscribe)
        {
            $current_message = isset($template_data['MESSAGE'])
                ? (string) $template_data['MESSAGE']
                : $plain_text_message;
            $template_data['MESSAGE'] = $this->append_text_footer($current_message, $unsubscribe_text_footer, $unsubscribe_url);
            $html_message = $this->append_html_footer($html_message, $unsubscribe_html_footer, $unsubscribe_url);
        }

        $event['template_data'] = $template_data;
        $this->mass_email_html_enabled = true;
        $this->mass_email_html_body = $this->wrap_html_email($html_message);
    }

    /**
     * Replace plain-text body with multipart alternative (text + html).
     */
    public function modify_notification_message($event)
    {
        $method = (int) $event['method'];
        if ($method !== NOTIFY_EMAIL && $method !== NOTIFY_BOTH)
        {
            $this->notification_email_context = false;
            return;
        }

        $message = (string) $event['message'];
        $this->notification_email_context = !$this->mass_email_context
            && $this->is_forum_notification_email_message($message);

        if ($this->notification_email_context)
        {
            $message_without_list_header = preg_replace('/^List-Unsubscribe(?:-Post)?:\s*.+\R?/mi', '', $message);
            if ($message_without_list_header !== null)
            {
                $message = ltrim($message_without_list_header, "\r\n");
                $event['message'] = $message;
            }
        }

        if (!$this->mass_email_html_enabled || $this->mass_email_html_body === '')
        {
            return;
        }

        $plain_text_message = $message;
        $plain_text_message = str_replace(["\r\n", "\r"], "\n", $plain_text_message);

        $this->mass_email_boundary = '=_adminhelper_' . md5(uniqid((string) mt_rand(), true));

        $this->load_language();
        $multipart_message = $this->language->lang('ADMINHELPER_MIME_MULTIPART_NOTICE') . "\r\n\r\n";
        $multipart_message .= '--' . $this->mass_email_boundary . "\r\n";
        $multipart_message .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $multipart_message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $multipart_message .= $plain_text_message . "\r\n\r\n";
        $multipart_message .= '--' . $this->mass_email_boundary . "\r\n";
        $multipart_message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $multipart_message .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $multipart_message .= $this->mass_email_html_body . "\r\n\r\n";
        $multipart_message .= '--' . $this->mass_email_boundary . '--';

        $event['message'] = $multipart_message;
    }

    /**
     * Build recipient-specific unsubscribe metadata before header generation.
     */
    public function notification_message_email($event)
    {
        $this->current_unsubscribe_one_click_url = '';
        $this->current_unsubscribe_type = '';

        try
        {
            if ((!$this->mass_email_context || !$this->mass_email_one_click_enabled) && !$this->notification_email_context)
            {
                return;
            }

            $addresses = isset($event['addresses']) && is_array($event['addresses'])
                ? $event['addresses']
                : [];
            $recipient = $this->extract_primary_email_recipient($addresses);
            if (!$recipient)
            {
                return;
            }

            $user_row = $this->find_user_for_recipient($recipient['email'], $recipient['name']);
            if (!$user_row)
            {
                return;
            }

            if ($this->mass_email_context && $this->mass_email_one_click_enabled)
            {
                $this->current_unsubscribe_type = 'massmail';
                $this->current_unsubscribe_one_click_url = $this->build_one_click_unsubscribe_url(
                    (int) $user_row['user_id'],
                    (string) $user_row['user_email'],
                    $this->current_unsubscribe_type,
                    (string) ($user_row['user_lang'] ?? '')
                );
                return;
            }

            if (!$this->notification_email_context)
            {
                return;
            }

            $this->apply_unsubscribe_language((string) ($user_row['user_lang'] ?? ''));
            $this->load_language();
            $this->current_unsubscribe_type = 'forum_notify';
            $this->current_unsubscribe_one_click_url = $this->build_one_click_unsubscribe_url(
                (int) $user_row['user_id'],
                (string) $user_row['user_email'],
                $this->current_unsubscribe_type,
                (string) ($user_row['user_lang'] ?? '')
            );

            $current_message = isset($event['msg']) ? (string) $event['msg'] : '';
            $unsubscribe_footer = $this->language->lang(
                'ADMINHELPER_NOTIFY_UNSUBSCRIBE_TEXT',
                $this->current_unsubscribe_one_click_url,
                $this->build_notification_preferences_url()
            );
            $event['msg'] = $this->append_text_footer(
                $current_message,
                $unsubscribe_footer,
                $this->current_unsubscribe_one_click_url
            );
        }
        catch (\Throwable $exception)
        {
            // Never block outgoing notifications because of unsubscribe header decoration.
            error_log('[AdminHelper] notification_message_email failed: ' . $exception->getMessage());
            $this->current_unsubscribe_one_click_url = '';
            $this->current_unsubscribe_type = '';
            $this->notification_email_context = false;
        }
    }

    /**
     * Throttle queue processing for one-click emails.
     */
    public function notification_message_process($event)
    {
        $headers = isset($event['headers']) && is_array($event['headers'])
            ? $event['headers']
            : [];

        foreach ($headers as $header)
        {
            if (stripos((string) $header, 'X-AdminHelper-OneClick: 1') === 0)
            {
                usleep(500000);
                return;
            }
        }
    }

    /**
     * Update content headers to multipart/alternative.
     */
    public function modify_email_headers($event)
    {
        try
        {
            $headers = isset($event['headers']) && is_array($event['headers'])
                ? $event['headers']
                : [];
            $filtered_headers = [];

            if ($this->mass_email_html_enabled && $this->mass_email_boundary !== '')
            {
                foreach ($headers as $header)
                {
                    $header = (string) $header;
                    if (stripos($header, 'Content-Type:') === 0 || stripos($header, 'Content-Transfer-Encoding:') === 0)
                    {
                        continue;
                    }

                    $filtered_headers[] = $header;
                }

                $filtered_headers[] = 'Content-Type: multipart/alternative; boundary="' . $this->mass_email_boundary . '"';
                $this->mass_email_boundary = '';
            }
            else
            {
                $filtered_headers = $headers;
            }

            $is_forum_notification = $this->notification_email_context && !$this->mass_email_context;
            if ($this->current_unsubscribe_one_click_url !== '' || $is_forum_notification)
            {
                $filtered_headers = $this->remove_headers_by_name($filtered_headers, [
                    'List-Unsubscribe:',
                    'List-Unsubscribe-Post:',
                    'X-AdminHelper-OneClick:',
                ]);

                $preferences_url = $this->current_unsubscribe_type === 'forum_notify' || $is_forum_notification
                    ? $this->build_notification_preferences_url()
                    : $this->build_profile_preferences_url();

                if ($this->current_unsubscribe_one_click_url !== '')
                {
                    $filtered_headers[] = 'List-Unsubscribe: <' . $this->current_unsubscribe_one_click_url . '>, <' . $preferences_url . '>';
                    $filtered_headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
                    $filtered_headers[] = 'X-AdminHelper-OneClick: 1';
                }
                else
                {
                    // Fallback to RFC 2369 even if one-click token cannot be built.
                    $filtered_headers[] = 'List-Unsubscribe: <' . $preferences_url . '>';
                }
            }

            $event['headers'] = $filtered_headers;
        }
        catch (\Throwable $exception)
        {
            // Fail open: preserve message delivery even if header decoration fails.
            error_log('[AdminHelper] modify_email_headers failed: ' . $exception->getMessage());
        }
        finally
        {
            $this->current_unsubscribe_one_click_url = '';
            $this->current_unsubscribe_type = '';
            $this->notification_email_context = false;
        }
    }

    /**
     * If only HTML is provided, generate a text fallback before core validation.
     */
    private function prepare_mass_email_message_fallback()
    {
        if ($this->request->variable('i', '') !== 'acp_email' || $this->request->variable('mode', '') !== 'email')
        {
            return;
        }

        if (!$this->request->is_set_post('submit'))
        {
            return;
        }

        if (!(bool) $this->request->variable('adminhelper_use_html', 0))
        {
            return;
        }

        $message = $this->request->variable('message', '', true);
        if ($message !== '')
        {
            return;
        }

        $html_message = $this->request->variable('adminhelper_message_html', '', true);
        if ($html_message === '')
        {
            return;
        }

        $html_message = html_entity_decode($html_message, ENT_COMPAT, 'UTF-8');
        $html_message = $this->sanitize_html_email($html_message);
        if ($html_message === '')
        {
            return;
        }

        $text_fallback = $this->html_to_text($html_message);
        if ($text_fallback === '')
        {
            return;
        }

        $this->request->overwrite('message', $text_fallback, request_interface::POST);
        $this->request->overwrite('message', $text_fallback, request_interface::REQUEST);
    }

    /**
     * Basic HTML cleanup for email content.
     */
    private function sanitize_html_email($html)
    {
        $html = trim((string) $html);
        if ($html === '')
        {
            return '';
        }

        // If a full HTML document is pasted, keep only the body payload.
        if (preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $body_matches))
        {
            $html = $body_matches[1];
        }

        $html = preg_replace('#<!doctype\b[^>]*>#is', '', $html);
        $html = preg_replace('#<\?xml\b[^>]*\?>#is', '', $html);
        $html = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html);
        $html = preg_replace('#<title\b[^>]*>.*?</title>#is', '', $html);
        $html = preg_replace('#</?(html|body)\b[^>]*>#is', '', $html);
        if ($html === null)
        {
            return '';
        }

        $blocked_tags = [
            '#<script\b[^>]*>.*?</script>#is',
            '#<iframe\b[^>]*>.*?</iframe>#is',
            '#<object\b[^>]*>.*?</object>#is',
            '#<embed\b[^>]*>.*?</embed>#is',
            '#<form\b[^>]*>.*?</form>#is',
            '#<link\b[^>]*>#is',
            '#<meta\b[^>]*>#is',
            '#<base\b[^>]*>#is',
        ];

        $html = preg_replace($blocked_tags, '', $html);
        if ($html === null)
        {
            return '';
        }

        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/isu', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/isu', '', $html);
        if ($html === null)
        {
            return '';
        }

        $html = preg_replace('/\sstyle\s*=\s*(["\']).*?(expression|javascript:).*?\1/isu', '', $html);
        if ($html === null)
        {
            return '';
        }

        $html = preg_replace_callback(
            '/\s(href|src)\s*=\s*(["\'])(.*?)\2/isu',
            function ($matches) {
                $decoded = trim(html_entity_decode($matches[3], ENT_QUOTES, 'UTF-8'));
                if (preg_match('#^(javascript:|data:)#i', $decoded))
                {
                    return '';
                }

                return ' ' . strtolower($matches[1]) . '=' . $matches[2] . $matches[3] . $matches[2];
            },
            $html
        );
        if ($html === null)
        {
            return '';
        }

        $allowed_tags = '<a><b><blockquote><br><code><div><em><h1><h2><h3><h4><h5><h6><hr><i><img><li><ol><p><pre><span><strong><table><tbody><thead><tfoot><tr><td><th><u><ul>';
        $html = strip_tags($html, $allowed_tags);

        return trim($html);
    }

    /**
     * Convert HTML into readable plain text for fallback part.
     */
    private function html_to_text($html)
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html);
        $text = preg_replace('#</(p|div|h1|h2|h3|h4|h5|h6|tr)>#i', "\n", $text);
        $text = preg_replace('#<li\b[^>]*>#i', '- ', $text);
        $text = preg_replace('#</li>#i', "\n", $text);

        if ($text === null)
        {
            return '';
        }

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        if ($text === null)
        {
            return '';
        }

        return trim($text);
    }

    /**
     * Ensure a valid HTML document body for email clients.
     */
    private function wrap_html_email($html_body)
    {
        return '<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html_body . '</body></html>';
    }

    private function build_profile_preferences_url()
    {
        if (function_exists('generate_board_url'))
        {
            return generate_board_url() . '/ucp.php?i=ucp_prefs&mode=personal';
        }

        return '/ucp.php?i=ucp_prefs&mode=personal';
    }

    private function build_notification_preferences_url()
    {
        if (function_exists('generate_board_url'))
        {
            return generate_board_url() . '/ucp.php?i=ucp_notifications&mode=notification_options';
        }

        return '/ucp.php?i=ucp_notifications&mode=notification_options';
    }

    private function append_text_footer($content, $footer, $dedupe_token = '')
    {
        $content = trim((string) $content);
        $footer = trim((string) $footer);

        if ($footer === '')
        {
            return $content;
        }

        if ($dedupe_token !== '' && strpos($content, $dedupe_token) !== false)
        {
            return $content;
        }

        if ($content === '')
        {
            return $footer;
        }

        return $content . "\n\n" . $footer;
    }

    private function append_html_footer($content, $footer, $dedupe_token = '')
    {
        $content = trim((string) $content);
        $footer = trim((string) $footer);

        if ($footer === '')
        {
            return $content;
        }

        if ($dedupe_token !== '' && strpos($content, $dedupe_token) !== false)
        {
            return $content;
        }

        if ($content === '')
        {
            return $footer;
        }

        return $content . "\n\n" . $footer;
    }

    private function is_acp_mass_email_submit()
    {
        return $this->request->variable('i', '') === 'acp_email'
            && $this->request->variable('mode', '') === 'email'
            && $this->request->is_set_post('submit');
    }

    private function handle_unsubscribe_request()
    {
        if (!(bool) $this->request->variable('adminhelper_unsub', 0))
        {
            return;
        }

        $user_id = (int) $this->request->variable('u', 0);
        $expires_at = (int) $this->request->variable('exp', 0);
        $signature = $this->normalize_unsubscribe_signature((string) $this->request->variable('sig', '', true));
        $unsubscribe_type = $this->normalize_unsubscribe_type((string) $this->request->variable('t', 'massmail', true));
        $requested_lang = $this->normalize_unsubscribe_language((string) $this->request->variable('lang', '', true));
        $log_context = [
            'user_id' => $user_id,
            'token_expires_at' => $expires_at,
            'unsubscribe_type' => $unsubscribe_type,
        ];

        if ($requested_lang !== '')
        {
            $this->apply_unsubscribe_language($requested_lang);
        }
        $this->load_language();

        if ($user_id <= 0 || $expires_at <= 0 || $signature === '')
        {
            $this->log_unsubscribe_event('invalid_request', 400, $log_context);
            $this->send_unsubscribe_text_response(400, $this->language->lang('ADMINHELPER_UNSUB_INVALID_REQUEST'));
        }

        $sql = 'SELECT user_id, user_email, user_allow_massemail, user_notify, user_notify_type, user_lang
            FROM ' . USERS_TABLE . '
            WHERE user_id = ' . (int) $user_id;
        $result = $this->db->sql_query($sql);
        $user_row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$user_row)
        {
            $this->log_unsubscribe_event('user_not_found', 403, $log_context);
            $this->send_unsubscribe_text_response(403, $this->language->lang('ADMINHELPER_UNSUB_INVALID_SIGNATURE'));
        }

        $log_context['user_id'] = (int) $user_row['user_id'];
        $log_context['user_email'] = (string) $user_row['user_email'];

        if ($requested_lang === '')
        {
            $this->apply_unsubscribe_language((string) ($user_row['user_lang'] ?? ''));
            $this->load_language();
        }

        $expected_signature = $this->build_unsubscribe_signature(
            (int) $user_row['user_id'],
            (string) $user_row['user_email'],
            $expires_at,
            $unsubscribe_type
        );
        $signature_is_valid = hash_equals($expected_signature, $signature);
        if (!$signature_is_valid && $unsubscribe_type === 'massmail')
        {
            $signature_is_valid = hash_equals(
                $this->build_legacy_unsubscribe_signature(
                    (int) $user_row['user_id'],
                    (string) $user_row['user_email'],
                    $expires_at
                ),
                $signature
            );
        }

        if (!$signature_is_valid)
        {
            $this->log_unsubscribe_event('invalid_signature', 403, $log_context);
            $this->send_unsubscribe_text_response(403, $this->language->lang('ADMINHELPER_UNSUB_INVALID_SIGNATURE'));
        }

        if ($expires_at < time())
        {
            $this->log_unsubscribe_event('expired_token', 410, $log_context);
            $this->send_unsubscribe_text_response(410, $this->language->lang('ADMINHELPER_UNSUB_EXPIRED'));
        }

        $is_post_request = strtoupper((string) $this->request->server('REQUEST_METHOD', 'GET')) === 'POST';
        if ($is_post_request)
        {
            if ($unsubscribe_type === 'forum_notify')
            {
                $was_subscribed = $this->is_user_notification_email_subscribed($user_row);

                if ($was_subscribed)
                {
                    $this->disable_user_notification_email((int) $user_row['user_id']);
                }

                $this->log_unsubscribe_event(
                    $was_subscribed ? 'unsubscribed' : 'already_unsubscribed',
                    200,
                    $log_context
                );

                $this->send_unsubscribe_text_response(
                    200,
                    $this->language->lang('ADMINHELPER_NOTIFY_UNSUB_DONE')
                );
            }

            $was_subscribed = (int) $user_row['user_allow_massemail'] !== 0;

            if ($was_subscribed)
            {
                $sql = 'UPDATE ' . USERS_TABLE . '
                    SET user_allow_massemail = 0
                    WHERE user_id = ' . (int) $user_row['user_id'];
                $this->db->sql_query($sql);
            }

            $this->log_unsubscribe_event(
                $was_subscribed ? 'unsubscribed' : 'already_unsubscribed',
                200,
                $log_context
            );

            $this->send_unsubscribe_text_response(
                200,
                $this->language->lang('ADMINHELPER_UNSUB_DONE')
            );
        }

        $action_url = htmlspecialchars(
            $this->build_one_click_unsubscribe_url(
                (int) $user_row['user_id'],
                (string) $user_row['user_email'],
                $unsubscribe_type,
                (string) ($user_row['user_lang'] ?? '')
            ),
            ENT_COMPAT,
            'UTF-8'
        );
        $html_title = $unsubscribe_type === 'forum_notify'
            ? $this->language->lang('ADMINHELPER_NOTIFY_UNSUB_PAGE_TITLE')
            : $this->language->lang('ADMINHELPER_UNSUB_PAGE_TITLE');
        $html_heading = $unsubscribe_type === 'forum_notify'
            ? $this->language->lang('ADMINHELPER_NOTIFY_UNSUB_PAGE_HEADING')
            : $this->language->lang('ADMINHELPER_UNSUB_PAGE_HEADING');
        $html_explain = $unsubscribe_type === 'forum_notify'
            ? $this->language->lang('ADMINHELPER_NOTIFY_UNSUB_PAGE_EXPLAIN')
            : $this->language->lang('ADMINHELPER_UNSUB_PAGE_EXPLAIN');
        $html_button = $unsubscribe_type === 'forum_notify'
            ? $this->language->lang('ADMINHELPER_NOTIFY_UNSUB_PAGE_BUTTON')
            : $this->language->lang('ADMINHELPER_UNSUB_PAGE_BUTTON');

        $html = '<!doctype html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($html_title, ENT_COMPAT, 'UTF-8') . '</title></head><body>'
            . '<h1>' . htmlspecialchars($html_heading, ENT_COMPAT, 'UTF-8') . '</h1>'
            . '<p>' . htmlspecialchars($html_explain, ENT_COMPAT, 'UTF-8') . '</p>'
            . '<form method="post" action="' . $action_url . '">'
            . '<button type="submit">' . htmlspecialchars($html_button, ENT_COMPAT, 'UTF-8') . '</button>'
            . '</form>'
            . '</body></html>';

        $this->log_unsubscribe_event('confirmation_page', 200, $log_context);
        $this->send_unsubscribe_html_response(200, $html);
    }

    private function build_one_click_unsubscribe_url($user_id, $user_email, $unsubscribe_type = 'massmail', $user_lang = '')
    {
        $expires_at = time() + 2592000;
        $unsubscribe_type = $this->normalize_unsubscribe_type($unsubscribe_type);
        $signature = $this->build_unsubscribe_signature((int) $user_id, (string) $user_email, $expires_at, $unsubscribe_type);
        $normalized_lang = $this->normalize_unsubscribe_language($user_lang);

        $base_url = function_exists('generate_board_url')
            ? generate_board_url() . '/index.php'
            : '/index.php';

        $params = [
            'adminhelper_unsub' => 1,
            'u' => (int) $user_id,
            'exp' => $expires_at,
            't' => $unsubscribe_type,
            'sig' => $signature,
        ];
        if ($normalized_lang !== '')
        {
            $params['lang'] = $normalized_lang;
        }

        return $base_url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function build_unsubscribe_signature($user_id, $user_email, $expires_at, $unsubscribe_type = 'massmail')
    {
        $data = (int) $user_id
            . '|'
            . strtolower(trim((string) $user_email))
            . '|'
            . (int) $expires_at
            . '|'
            . $this->normalize_unsubscribe_type($unsubscribe_type);
        return hash_hmac('sha256', $data, $this->get_unsubscribe_secret());
    }

    private function build_legacy_unsubscribe_signature($user_id, $user_email, $expires_at)
    {
        $data = (int) $user_id . '|' . strtolower(trim((string) $user_email)) . '|' . (int) $expires_at;
        return hash_hmac('sha256', $data, $this->get_unsubscribe_secret());
    }

    private function normalize_unsubscribe_type($unsubscribe_type)
    {
        $unsubscribe_type = strtolower(trim((string) $unsubscribe_type));
        return $unsubscribe_type === 'forum_notify' ? 'forum_notify' : 'massmail';
    }

    private function normalize_unsubscribe_language($language_iso)
    {
        $language_iso = strtolower(trim((string) $language_iso));
        if ($language_iso === '' || !preg_match('/^[a-z][a-z0-9_]{1,15}$/', $language_iso))
        {
            return '';
        }

        return $language_iso;
    }

    private function apply_unsubscribe_language($language_iso)
    {
        $language_iso = $this->normalize_unsubscribe_language($language_iso);
        if ($language_iso === '')
        {
            return;
        }

        if (!method_exists($this->language, 'set_user_language'))
        {
            return;
        }

        try
        {
            $this->language->set_user_language($language_iso);
        }
        catch (\Throwable $exception)
        {
            // Ne jamais bloquer un email ou une desinscription pour un souci de langue.
        }
    }

    private function normalize_unsubscribe_signature($signature)
    {
        $signature = strtolower(trim((string) $signature));
        if ($signature === '')
        {
            return '';
        }

        if (preg_match('/^([a-f0-9]{64})/i', $signature, $matches))
        {
            return strtolower($matches[1]);
        }

        return $signature;
    }

    private function is_forum_notification_email_message($message)
    {
        $message = (string) $message;
        if ($message === '')
        {
            return false;
        }

        if (preg_match('/^List-Unsubscribe:\s*<[^>]+>/mi', $message))
        {
            return true;
        }

        return strpos($message, 'i=ucp_notifications&mode=notification_options') !== false
            || strpos($message, 'i=ucp_notifications&amp;mode=notification_options') !== false;
    }

    private function get_unsubscribe_secret()
    {
        global $config;

        $cookie_name = $this->config ? (string) $this->config['cookie_name'] : (string) $config['cookie_name'];
        $rand_seed = $this->config ? (string) $this->config['rand_seed'] : (string) $config['rand_seed'];
        $board_email = $this->config ? (string) $this->config['board_email'] : (string) $config['board_email'];

        return hash(
            'sha256',
            $cookie_name
            . '|'
            . $rand_seed
            . '|'
            . $board_email
        );
    }

    private function extract_primary_email_recipient(array $addresses)
    {
        foreach (['to', 'bcc', 'cc'] as $channel)
        {
            if (empty($addresses[$channel]) || !is_array($addresses[$channel]))
            {
                continue;
            }

            foreach ($addresses[$channel] as $recipient)
            {
                $email = isset($recipient['email']) ? strtolower(trim((string) $recipient['email'])) : '';
                if ($email === '')
                {
                    continue;
                }

                return [
                    'email' => $email,
                    'name' => isset($recipient['name']) ? (string) $recipient['name'] : '',
                ];
            }
        }

        return false;
    }

    private function find_user_id_by_email($email)
    {
        $email = utf8_strtolower(trim((string) $email));
        if ($email === '')
        {
            return 0;
        }

        $sql = 'SELECT user_id
            FROM ' . USERS_TABLE . "
            WHERE LOWER(user_email) = '" . $this->db->sql_escape($email) . "'
            ORDER BY user_id ASC";
        $result = $this->db->sql_query_limit($sql, 1);
        $user_id = (int) $this->db->sql_fetchfield('user_id');
        $this->db->sql_freeresult($result);

        return $user_id;
    }

    private function find_user_for_recipient($email, $name)
    {
        $cache_key = strtolower(trim((string) $email)) . '|' . strtolower(trim((string) $name));
        if (array_key_exists($cache_key, $this->recipient_user_cache))
        {
            return $this->recipient_user_cache[$cache_key];
        }

        $email = trim((string) $email);
        $name = trim((string) $name);
        if ($email === '')
        {
            $this->recipient_user_cache[$cache_key] = false;
            return false;
        }

        $user_row = false;
        if ($name !== '')
        {
            $sql = 'SELECT user_id, user_email, user_notify, user_notify_type, user_lang
                FROM ' . USERS_TABLE . "
                WHERE user_email = '" . $this->db->sql_escape($email) . "'
                    AND username = '" . $this->db->sql_escape($name) . "'";
            $result = $this->db->sql_query_limit($sql, 1);
            $user_row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
        }

        if (!$user_row)
        {
            $sql = 'SELECT user_id, user_email, user_notify, user_notify_type, user_lang
                FROM ' . USERS_TABLE . "
                WHERE user_email = '" . $this->db->sql_escape($email) . "'
                ORDER BY user_id ASC";
            $result = $this->db->sql_query_limit($sql, 1);
            $user_row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
        }

        $this->recipient_user_cache[$cache_key] = $user_row ? $user_row : false;
        return $this->recipient_user_cache[$cache_key];
    }

    private function remove_headers_by_name(array $headers, array $prefixes)
    {
        $filtered_headers = [];

        foreach ($headers as $header)
        {
            $header = (string) $header;
            $skip = false;

            foreach ($prefixes as $prefix)
            {
                if (stripos($header, $prefix) === 0)
                {
                    $skip = true;
                    break;
                }
            }

            if (!$skip)
            {
                $filtered_headers[] = $header;
            }
        }

        return $filtered_headers;
    }

    private function is_user_notification_email_subscribed(array $user_row)
    {
        $user_id = (int) ($user_row['user_id'] ?? 0);
        if ($user_id <= 0)
        {
            return false;
        }

        $legacy_email_enabled = (int) ($user_row['user_notify'] ?? 0) === 1
            && in_array((int) ($user_row['user_notify_type'] ?? NOTIFY_EMAIL), [NOTIFY_EMAIL, NOTIFY_BOTH], true);
        if ($legacy_email_enabled)
        {
            return true;
        }

        $sql = 'SELECT 1 AS is_enabled
            FROM ' . USER_NOTIFICATIONS_TABLE . "
            WHERE user_id = " . $user_id . "
                AND item_id = 0
                AND method = 'notification.method.email'
                AND notify = 1";
        $result = $this->db->sql_query_limit($sql, 1);
        $has_email_method_enabled = (bool) $this->db->sql_fetchfield('is_enabled');
        $this->db->sql_freeresult($result);
        if ($has_email_method_enabled)
        {
            return true;
        }

        // Missing per-type rows fallback to phpBB default methods (email usually enabled).
        $sql = 'SELECT 1 AS has_missing_preference
            FROM ' . NOTIFICATION_TYPES_TABLE . ' nt
            LEFT JOIN ' . USER_NOTIFICATIONS_TABLE . " un
                ON un.user_id = " . $user_id . "
                    AND un.item_type = nt.notification_type_name
                    AND un.item_id = 0
                    AND un.method = 'notification.method.email'
            WHERE nt.notification_type_enabled = 1
                AND un.user_id IS NULL";
        $result = $this->db->sql_query_limit($sql, 1);
        $has_missing_preference = (bool) $this->db->sql_fetchfield('has_missing_preference');
        $this->db->sql_freeresult($result);

        return $has_missing_preference;
    }

    private function disable_user_notification_email($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0)
        {
            return;
        }

        $sql = 'UPDATE ' . USERS_TABLE . '
            SET user_notify = 0,
                user_notify_type = CASE
                    WHEN user_notify_type = ' . NOTIFY_EMAIL . ' THEN ' . NOTIFY_IM . '
                    WHEN user_notify_type = ' . NOTIFY_BOTH . ' THEN ' . NOTIFY_IM . '
                    ELSE user_notify_type
                END
            WHERE user_id = ' . $user_id;
        $this->db->sql_query($sql);

        $this->ensure_global_email_notification_rows($user_id);

        $sql = 'UPDATE ' . USER_NOTIFICATIONS_TABLE . "
            SET notify = 0
            WHERE user_id = " . $user_id . "
                AND method = 'notification.method.email'";
        $this->db->sql_query($sql);
    }

    private function ensure_global_email_notification_rows($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0)
        {
            return;
        }

        $missing_rows = [];

        $sql = 'SELECT nt.notification_type_name
            FROM ' . NOTIFICATION_TYPES_TABLE . ' nt
            LEFT JOIN ' . USER_NOTIFICATIONS_TABLE . " un
                ON un.user_id = " . $user_id . "
                    AND un.item_type = nt.notification_type_name
                    AND un.item_id = 0
                    AND un.method = 'notification.method.email'
            WHERE nt.notification_type_enabled = 1
                AND un.user_id IS NULL";
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $missing_rows[] = [
                'item_type' => (string) $row['notification_type_name'],
                'item_id' => 0,
                'user_id' => $user_id,
                'method' => 'notification.method.email',
                'notify' => 0,
            ];
        }
        $this->db->sql_freeresult($result);

        if (!empty($missing_rows))
        {
            $this->db->sql_multi_insert(USER_NOTIFICATIONS_TABLE, $missing_rows);
        }
    }

    private function log_unsubscribe_event($status, $http_status, array $context = [])
    {
        if (!$this->is_unsubscribe_log_enabled())
        {
            return;
        }

        $request_method = strtoupper((string) $this->request->server('REQUEST_METHOD', 'GET'));
        $request_ip = (string) $this->request->server('REMOTE_ADDR', '');
        $request_user_agent = (string) $this->request->server('HTTP_USER_AGENT', '');
        $status = strtolower(trim((string) $status));

        if (
            in_array($status, ['invalid_request', 'user_not_found', 'invalid_signature', 'expired_token'], true)
            && $this->is_unsubscribe_log_rate_limited($request_ip, $status)
        ) {
            return;
        }

        $log_data = [
            'user_id' => isset($context['user_id']) ? (int) $context['user_id'] : 0,
            'user_email' => isset($context['user_email']) ? substr(trim((string) $context['user_email']), 0, 255) : '',
            'unsubscribe_type' => substr($this->normalize_unsubscribe_type($context['unsubscribe_type'] ?? 'massmail'), 0, 32),
            'token_expires_at' => isset($context['token_expires_at']) ? (int) $context['token_expires_at'] : 0,
            'http_status' => (int) $http_status,
            'event_status' => substr($status, 0, 32),
            'request_method' => substr($request_method, 0, 8),
            'request_ip' => substr($request_ip, 0, 40),
            'request_user_agent' => substr($request_user_agent, 0, 255),
            'logged_at' => time(),
        ];

        $sql = 'INSERT INTO ' . $this->get_unsubscribe_log_table() . ' '
            . $this->db->sql_build_array('INSERT', $log_data);
        $this->db->sql_query($sql);
    }

    private function is_unsubscribe_log_rate_limited($request_ip, $status)
    {
        $request_ip = substr(trim((string) $request_ip), 0, 40);
        $status = substr(strtolower(trim((string) $status)), 0, 32);
        if ($request_ip === '' || $status === '')
        {
            return false;
        }

        // Limit repeated invalid events per IP to reduce log-flood abuse.
        $window_seconds = 300;
        $max_events = 20;

        $sql = 'SELECT COUNT(log_id) AS total_events
            FROM ' . $this->get_unsubscribe_log_table() . "
            WHERE request_ip = '" . $this->db->sql_escape($request_ip) . "'
                AND event_status = '" . $this->db->sql_escape($status) . "'
                AND logged_at >= " . (time() - $window_seconds);
        $result = $this->db->sql_query($sql);
        $total_events = (int) $this->db->sql_fetchfield('total_events');
        $this->db->sql_freeresult($result);

        return $total_events >= $max_events;
    }

    private function is_unsubscribe_log_enabled()
    {
        global $config;

        if ($this->config && $this->config->offsetExists('adminhelper_unsub_log_enabled'))
        {
            return (bool) $this->config['adminhelper_unsub_log_enabled'];
        }

        if (isset($config['adminhelper_unsub_log_enabled']))
        {
            return (bool) $config['adminhelper_unsub_log_enabled'];
        }

        return false;
    }

    private function get_unsubscribe_log_table()
    {
        global $table_prefix;

        return $table_prefix . 'adminhelper_unsubscribe_log';
    }

    private function send_unsubscribe_text_response($status_code, $body)
    {
        $this->send_unsubscribe_http_response((int) $status_code, 'text/plain; charset=UTF-8', (string) $body);
    }

    private function send_unsubscribe_html_response($status_code, $body)
    {
        $this->send_unsubscribe_http_response((int) $status_code, 'text/html; charset=UTF-8', (string) $body);
    }

    private function send_unsubscribe_http_response($status_code, $content_type, $body)
    {
        http_response_code((int) $status_code);
        header('Content-Type: ' . $content_type);
        header('X-Robots-Tag: noindex, nofollow');
        echo $body;
        exit;
    }

    /**
     * Recherche par auteur : prépare le rendu complet des posts et capture les PJ.
     *
     * Déclenché sur core.search_modify_rowset, avant que phpBB traite les PJ inline
     * et tronque les textes. On :
     *  1. Détecte si c'est une recherche sr=posts&author_id=N
     *  2. Force display_text_only=false sur tous les posts (texte BBCode complet)
     *  3. Re-fetch le BBCode original pour les posts déjà tronqués
     *  4. Capture toutes les PJ brutes (DB rows) dans $this->search_post_attaches
     *  5. Injecte dans $attachments les PJ des posts qui n'y étaient pas (car tronqués)
     */
    public function on_search_author_rowset($event)
    {
        $author_id = $this->request->variable('author_id', 0);
        $sr        = $this->request->variable('sr', '');

        if ($author_id <= 0 || $sr !== 'posts')
        {
            return;
        }

        $this->search_is_author = true;

        $rowset      = $event['rowset'];
        $attachments = $event['attachments'];

        // Stocker les PJ déjà chargées par phpBB
        foreach ($attachments as $post_id => $attach_rows)
        {
            $this->search_post_attaches[(int) $post_id] = $attach_rows;
        }

        // Trouver les posts tronqués (display_text_only=true → BBCode perdu)
        $refetch_ids  = [];
        $missing_pj   = [];

        foreach ($rowset as $key => $row)
        {
            $post_id = (int) $row['post_id'];

            if (!empty($row['display_text_only']))
            {
                $refetch_ids[$key] = $post_id;
            }

            if (!empty($row['post_attachment']) && !isset($this->search_post_attaches[$post_id]))
            {
                $missing_pj[] = $post_id;
            }
        }

        // Re-fetch BBCode original pour les posts tronqués
        if (!empty($refetch_ids))
        {
            $sql = 'SELECT post_id, post_text, bbcode_uid, bbcode_bitfield
                    FROM ' . POSTS_TABLE . '
                    WHERE ' . $this->db->sql_in_set('post_id', array_values($refetch_ids));
            $result = $this->db->sql_query($sql);

            $originals = [];
            while ($orig = $this->db->sql_fetchrow($result))
            {
                $originals[(int) $orig['post_id']] = $orig;
            }
            $this->db->sql_freeresult($result);

            foreach ($refetch_ids as $key => $post_id)
            {
                if (!empty($originals[$post_id]))
                {
                    $rowset[$key]['post_text']      = $originals[$post_id]['post_text'];
                    $rowset[$key]['bbcode_uid']      = $originals[$post_id]['bbcode_uid'];
                    $rowset[$key]['bbcode_bitfield'] = $originals[$post_id]['bbcode_bitfield'];
                }
                $rowset[$key]['display_text_only'] = false;
            }
        }

        // Charger les PJ manquantes (posts qui étaient tronqués, donc absents de $attachments)
        if (!empty($missing_pj))
        {
            $sql = 'SELECT *
                    FROM ' . ATTACHMENTS_TABLE . '
                    WHERE ' . $this->db->sql_in_set('post_msg_id', $missing_pj) . '
                        AND is_orphan = 0
                    ORDER BY filetime DESC, post_msg_id ASC';
            $result = $this->db->sql_query($sql);

            while ($attach = $this->db->sql_fetchrow($result))
            {
                $post_id = (int) $attach['post_msg_id'];
                $this->search_post_attaches[$post_id][] = $attach;
                $attachments[$post_id][]                = $attach;
            }
            $this->db->sql_freeresult($result);
        }

        $event['rowset']      = $rowset;
        $event['attachments'] = $attachments;
    }

    /**
     * Recherche par auteur : ajoute les PJ non-inline dans le tpl_ary de chaque post.
     *
     * Une PJ est "non-inline" si son attach_id n'apparaît pas dans le HTML rendu
     * du MESSAGE (les inline ont été remplacées par des <img> ou liens contenant
     * "id=N" dans l'URL download/file.php).
     */
    public function on_search_author_tpl_ary($event)
    {
        if (!$this->search_is_author || $event['show_results'] !== 'posts')
        {
            return;
        }

        global $phpbb_root_path, $phpEx;

        $row     = $event['row'];
        $post_id = (int) $row['post_id'];
        $attaches = $this->search_post_attaches[$post_id] ?? [];

        if (empty($attaches))
        {
            return;
        }

        $tpl_ary = $event['tpl_ary'];
        $message = $tpl_ary['MESSAGE'] ?? '';

        $html = '';
        foreach ($attaches as $attach)
        {
            $attach_id = (int) $attach['attach_id'];

            // Pièce jointe inline = son ID apparaît déjà dans l'URL du MESSAGE rendu
            if (strpos($message, 'id=' . $attach_id) !== false)
            {
                continue;
            }

            $filename = htmlspecialchars($attach['real_filename'], ENT_QUOTES, 'UTF-8');
            $filesize = $this->format_attach_filesize((int) $attach['filesize']);
            $dl_url   = htmlspecialchars(
                $phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id,
                ENT_QUOTES, 'UTF-8'
            );

            if (!empty($attach['thumbnail']))
            {
                $thumb_url = htmlspecialchars(
                    $phpbb_root_path . 'download/file.' . $phpEx . '?id=' . $attach_id . '&amp;t=1',
                    ENT_QUOTES, 'UTF-8'
                );
                $html .= '<div class="adminhelper-attach-item">'
                    . '<a href="' . $dl_url . '" target="_blank">'
                    . '<img src="' . $thumb_url . '" alt="' . $filename . '" class="adminhelper-attach-thumb">'
                    . ' ' . $filename
                    . '</a>'
                    . ' <span class="adminhelper-attach-size">(' . $filesize . ')</span>'
                    . '</div>';
            }
            elseif (strpos($attach['mimetype'], 'image/') === 0)
            {
                $html .= '<div class="adminhelper-attach-item">'
                    . '<a href="' . $dl_url . '" target="_blank">'
                    . '<img src="' . $dl_url . '" alt="' . $filename . '" class="adminhelper-attach-thumb">'
                    . ' ' . $filename
                    . '</a>'
                    . ' <span class="adminhelper-attach-size">(' . $filesize . ')</span>'
                    . '</div>';
            }
            else
            {
                $icon = $this->get_attach_fa_icon($attach['mimetype']);
                $html .= '<div class="adminhelper-attach-item">'
                    . '<i class="icon ' . $icon . ' fa-fw" aria-hidden="true"></i> '
                    . '<a href="' . $dl_url . '" target="_blank">' . $filename . '</a>'
                    . ' <span class="adminhelper-attach-size">(' . $filesize . ')</span>'
                    . '</div>';
            }
        }

        if ($html !== '')
        {
            $tpl_ary['ADMINHELPER_SEARCH_ATTACHES'] = $html;
            $event['tpl_ary'] = $tpl_ary;
        }
    }

    private function format_attach_filesize($bytes)
    {
        if ($bytes < 1024)
        {
            return $bytes . ' B';
        }
        if ($bytes < 1048576)
        {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    private function get_attach_fa_icon($mime)
    {
        if (strpos($mime, 'pdf') !== false)
        {
            return 'fa-file-pdf-o';
        }
        if (strpos($mime, 'zip') !== false || strpos($mime, 'rar') !== false || strpos($mime, 'archive') !== false)
        {
            return 'fa-file-archive-o';
        }
        if (strpos($mime, 'video/') === 0)
        {
            return 'fa-file-video-o';
        }
        if (strpos($mime, 'audio/') === 0)
        {
            return 'fa-file-audio-o';
        }
        if (strpos($mime, 'text/') === 0)
        {
            return 'fa-file-text-o';
        }
        return 'fa-paperclip';
    }

    private function get_requested_attachment_ids()
    {
        $raw = $this->request->variable('attachment_data', [['attach_id' => 0]], true);
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $attach_id = (int) ($entry['attach_id'] ?? 0);
            if ($attach_id > 0) {
                $ids[$attach_id] = $attach_id;
            }
        }

        return array_values($ids);
    }

    private function load_language()
    {
        if ($this->language_loaded)
        {
            return;
        }

        $this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');
        $this->language_loaded = true;
    }

    /**
     * core.page_header — injecte ADMINHELPER_IS_MOD_OR_ADMIN dans le template.
     *
     * IMPORTANT : on NE PAS appeler add_form_key() ici — cette fonction phpBB
     * écrase {S_FORM_TOKEN} globalement, ce qui casserait posting.php, ucp, etc.
     * On génère le token CSRF manuellement dans une variable dédiée.
     */
    public function on_page_header($event)
    {
        $is_mod = $this->auth->acl_getf_global('m_edit') || $this->auth->acl_get('a_');

        $this->template->assign_vars(['ADMINHELPER_IS_MOD_OR_ADMIN' => $is_mod]);

        if (!$is_mod)
        {
            return;
        }

        $this->load_language();

        // Générer le token CSRF manuellement (même algorithme que add_form_key)
        // sans appeler add_form_key() qui écraserait S_FORM_TOKEN sur toutes les pages.
        $now = time();
        $token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests']))
            ? $this->user->data['session_id'] : '';
        $token = sha1($now . $this->user->data['user_form_salt'] . 'adminhelper_mod_note' . $token_sid);

        $csrf_fields = '<input type="hidden" name="creation_time" value="' . $now . '">'
            . '<input type="hidden" name="form_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';

        $this->template->assign_vars(['ADMINHELPER_MOD_NOTE_FORM_TOKEN' => $csrf_fields]);
    }

    /**
     * core.viewtopic_post_row_after — charge la note de modération du post courant
     * et l'injecte dans le bloc postrow via alter_block_array.
     */
    public function on_viewtopic_post_row($event)
    {
        $row      = $event['row'];
        $post_id  = (int) $row['post_id'];
        $forum_id = (int) $row['forum_id'];

        $can_note = $this->auth->acl_get('m_edit', $forum_id) || $this->auth->acl_get('a_');

        if (!$can_note)
        {
            return;
        }

        $this->load_language();

        // Charger la note existante pour ce post
        $sql = 'SELECT n.note_id, n.note_text, n.note_created, u.username AS note_author
                FROM ' . $this->mod_notes_table . ' n
                LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = n.note_author_id
                WHERE n.post_id = ' . $post_id;
        $result = $this->db->sql_query($sql);
        $note   = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        // Construire les URLs des actions
        $board_url   = generate_board_url();
        $u_save      = $board_url . '/app.php/adminhelper/mod-notes/save';
        $u_delete    = $note
            ? $board_url . '/app.php/adminhelper/mod-notes/delete/' . (int) $note['note_id']
            : '';

        $note_by = '';
        if ($note)
        {
            // Échapper le pseudo : autoescape Twig est OFF dans phpBB 3.3
            $safe_author = htmlspecialchars($note['note_author'], ENT_QUOTES, 'UTF-8');
            $note_by = $this->language->lang(
                'ADMINHELPER_MOD_NOTE_BY',
                $safe_author,
                $this->user->format_date((int) $note['note_created'])
            );
        }

        $this->template->alter_block_array('postrow', [
            'ADMINHELPER_CAN_MOD_NOTE'  => true,
            'ADMINHELPER_HAS_NOTE'      => !empty($note),
            // Échapper le texte de la note (XSS stocké — autoescape OFF dans phpBB 3.3)
            'ADMINHELPER_NOTE_TEXT'     => $note ? htmlspecialchars($note['note_text'], ENT_QUOTES, 'UTF-8') : '',
            'ADMINHELPER_NOTE_BY'       => $note_by,
            'ADMINHELPER_NOTE_ID'       => $note ? (int) $note['note_id'] : 0,
            'ADMINHELPER_U_SAVE_NOTE'   => $u_save,
            'ADMINHELPER_U_DELETE_NOTE' => $u_delete,
            'ADMINHELPER_POST_ID_NOTE'  => $post_id,
        ], true, 'change');
    }

    /**
     * Normalise les entités HTML sur-encodées dans les URLs BBCode avant stockage.
     * Corrige les cas de copier-coller depuis du HTML ou des apps de messagerie
     * qui peuvent encoder & → &amp; plusieurs fois avant soumission au forum.
     */
    public function normalize_post_urls($event)
    {
        $data = $event['data'];
        $message = $data['message'];
        $message = $this->fix_bbcode_url_encoding($message);
        $data['message'] = $message;
        $event['data'] = $data;
    }

    private function fix_bbcode_url_encoding(string $text): string
    {
        // [url=https://...] et [url="https://..."]
        $text = preg_replace_callback(
            '/\[url=([^\]]*)\]/i',
            function ($m) {
                return '[url=' . $this->decode_entities_url($m[1]) . ']';
            },
            $text
        );

        // [img]https://...[/img]
        $text = preg_replace_callback(
            '/(\[img\])(https?:\/\/[^\[]+)(\[\/img\])/i',
            function ($m) {
                return $m[1] . $this->decode_entities_url($m[2]) . $m[3];
            },
            $text
        );

        // [url]https://...[/url]
        $text = preg_replace_callback(
            '/(\[url\])(https?:\/\/[^\[]+)(\[\/url\])/i',
            function ($m) {
                return $m[1] . $this->decode_entities_url($m[2]) . $m[3];
            },
            $text
        );

        return $text;
    }

    private function decode_entities_url(string $url): string
    {
        $prev = null;
        while ($prev !== $url) {
            $prev = $url;
            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $url;
    }
}
