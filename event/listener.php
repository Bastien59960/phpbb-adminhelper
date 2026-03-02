<?php
namespace bastien59960\adminhelper\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\request\request_interface;

class listener implements EventSubscriberInterface
{
    protected $db;
    protected $request;
    protected $template;
    protected $language;
    protected $config;
    protected $language_loaded;
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

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\request\request $request,
        \phpbb\template\template $template,
        \phpbb\language\language $language,
        ?\phpbb\config\config $config = null
    ) {
        $this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->language = $language;
        $this->config = $config;
        $this->language_loaded = false;
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
        ];
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

        if (!defined('IN_ADMIN') || $this->request->variable('i', '') !== 'acp_users')
        {
            return;
        }

        global $auth;
        if (!isset($auth) || !$auth->acl_get('a_user'))
        {
            return;
        }

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
        // Safety rule: keep automatic forum notifications untouched.
        // AdminHelper one-click headers now apply only to ACP mass emails.
        $this->notification_email_context = false;

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

        if (!$this->mass_email_context || !$this->mass_email_one_click_enabled)
        {
            return;
        }

        $addresses = (array) $event['addresses'];
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

        $this->current_unsubscribe_type = 'massmail';
        $this->current_unsubscribe_one_click_url = $this->build_one_click_unsubscribe_url(
            (int) $user_row['user_id'],
            (string) $user_row['user_email'],
            $this->current_unsubscribe_type
        );
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
        $headers = (array) $event['headers'];
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

        if ($this->current_unsubscribe_one_click_url !== '')
        {
            $filtered_headers = $this->remove_headers_by_name($filtered_headers, [
                'List-Unsubscribe:',
                'List-Unsubscribe-Post:',
                'X-AdminHelper-OneClick:',
            ]);

            $preferences_url = $this->current_unsubscribe_type === 'forum_notify'
                ? $this->build_notification_preferences_url()
                : $this->build_profile_preferences_url();

            $filtered_headers[] = 'List-Unsubscribe: <' . $this->current_unsubscribe_one_click_url . '>, <' . $preferences_url . '>';
            $filtered_headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
            $filtered_headers[] = 'X-AdminHelper-OneClick: 1';
        }

        $event['headers'] = $filtered_headers;
        $this->current_unsubscribe_one_click_url = '';
        $this->current_unsubscribe_type = '';
        $this->notification_email_context = false;
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
        $signature = (string) $this->request->variable('sig', '', true);
        $unsubscribe_type = $this->normalize_unsubscribe_type((string) $this->request->variable('t', 'massmail', true));
        $log_context = [
            'user_id' => $user_id,
            'token_expires_at' => $expires_at,
            'unsubscribe_type' => $unsubscribe_type,
        ];
        $this->load_language();

        if ($user_id <= 0 || $expires_at <= 0 || $signature === '')
        {
            $this->log_unsubscribe_event('invalid_request', 400, $log_context);
            $this->send_unsubscribe_text_response(400, $this->language->lang('ADMINHELPER_UNSUB_INVALID_REQUEST'));
        }

        $sql = 'SELECT user_id, user_email, user_allow_massemail, user_notify, user_notify_type
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
                $unsubscribe_type
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

    private function build_one_click_unsubscribe_url($user_id, $user_email, $unsubscribe_type = 'massmail')
    {
        $expires_at = time() + 2592000;
        $unsubscribe_type = $this->normalize_unsubscribe_type($unsubscribe_type);
        $signature = $this->build_unsubscribe_signature((int) $user_id, (string) $user_email, $expires_at, $unsubscribe_type);

        $base_url = function_exists('generate_board_url')
            ? generate_board_url() . '/index.php'
            : '/index.php';

        return $base_url . '?' . http_build_query([
            'adminhelper_unsub' => 1,
            'u' => (int) $user_id,
            'exp' => $expires_at,
            't' => $unsubscribe_type,
            'sig' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);
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
            $sql = 'SELECT user_id, user_email, user_notify, user_notify_type
                FROM ' . USERS_TABLE . "
                WHERE user_email = '" . $this->db->sql_escape($email) . "'
                    AND username = '" . $this->db->sql_escape($name) . "'";
            $result = $this->db->sql_query_limit($sql, 1);
            $user_row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
        }

        if (!$user_row)
        {
            $sql = 'SELECT user_id, user_email, user_notify, user_notify_type
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
        $legacy_email_enabled = (int) ($user_row['user_notify'] ?? 0) === 1
            && in_array((int) ($user_row['user_notify_type'] ?? NOTIFY_EMAIL), [NOTIFY_EMAIL, NOTIFY_BOTH], true);

        $sql = 'SELECT 1 AS is_enabled
            FROM ' . USER_NOTIFICATIONS_TABLE . "
            WHERE user_id = " . (int) $user_row['user_id'] . "
                AND method = 'notification.method.email'
                AND notify = 1";
        $result = $this->db->sql_query_limit($sql, 1);
        $has_email_method_enabled = (bool) $this->db->sql_fetchfield('is_enabled');
        $this->db->sql_freeresult($result);

        return $legacy_email_enabled || $has_email_method_enabled;
    }

    private function disable_user_notification_email($user_id)
    {
        $sql = 'UPDATE ' . USERS_TABLE . '
            SET user_notify = 0,
                user_notify_type = CASE
                    WHEN user_notify_type = ' . NOTIFY_EMAIL . ' THEN ' . NOTIFY_IM . '
                    WHEN user_notify_type = ' . NOTIFY_BOTH . ' THEN ' . NOTIFY_IM . '
                    ELSE user_notify_type
                END
            WHERE user_id = ' . (int) $user_id;
        $this->db->sql_query($sql);

        $sql = 'UPDATE ' . USER_NOTIFICATIONS_TABLE . "
            SET notify = 0
            WHERE user_id = " . (int) $user_id . "
                AND method = 'notification.method.email'";
        $this->db->sql_query($sql);
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

    private function load_language()
    {
        if ($this->language_loaded)
        {
            return;
        }

        $this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');
        $this->language_loaded = true;
    }
}
