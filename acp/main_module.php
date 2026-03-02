<?php
/**
 * Admin Helper Extension - ACP module
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $db, $template, $user, $request, $table_prefix, $phpbb_container, $phpbb_root_path, $phpEx;

        $user->add_lang_ext('bastien59960/adminhelper', 'acp/info_acp_adminhelper');

        $this->tpl_name = 'acp_adminhelper_unsubscribe_log';
        $this->page_title = $user->lang('ACP_ADMINHELPER_UNSUBSCRIBE_LOGS');

        $log_table = $table_prefix . 'adminhelper_unsubscribe_log';
        $db_tools = $phpbb_container->get('dbal.tools');
        if (!$db_tools->sql_table_exists($log_table))
        {
            trigger_error($user->lang('ACP_ADMINHELPER_LOG_TABLE_MISSING') . adm_back_link($this->u_action), E_USER_WARNING);
        }

        $start = max(0, (int) $request->variable('start', 0));
        $per_page = 50;
        add_form_key('adminhelper_unsubscribe_logs');

        if ($request->is_set_post('delete_logs_all') || $request->is_set_post('delete_logs_selected'))
        {
            if (!check_form_key('adminhelper_unsubscribe_logs'))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            if ($request->is_set_post('delete_logs_all'))
            {
                $sql = 'DELETE FROM ' . $log_table;
                $db->sql_query($sql);
                $deleted_logs = (int) $db->sql_affectedrows();

                trigger_error($user->lang('ACP_ADMINHELPER_DELETE_ALL_SUCCESS', $deleted_logs) . adm_back_link($this->u_action));
            }

            $selected_log_ids = [];
            foreach ($request->variable('log_ids', [0]) as $log_id)
            {
                $log_id = (int) $log_id;
                if ($log_id > 0)
                {
                    $selected_log_ids[$log_id] = $log_id;
                }
            }
            $selected_log_ids = array_values($selected_log_ids);

            if (empty($selected_log_ids))
            {
                trigger_error($user->lang('ACP_ADMINHELPER_DELETE_NONE_SELECTED') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sql = 'DELETE FROM ' . $log_table . '
                WHERE ' . $db->sql_in_set('log_id', $selected_log_ids);
            $db->sql_query($sql);
            $deleted_logs = (int) $db->sql_affectedrows();

            trigger_error($user->lang('ACP_ADMINHELPER_DELETE_SELECTED_SUCCESS', $deleted_logs) . adm_back_link($this->u_action));
        }

        $action = (string) $request->variable('action', '');
        if ($action === 'restore_notify')
        {
            $restore_user_id = (int) $request->variable('uid', 0);
            $hash = (string) $request->variable('hash', '', true);

            if ($restore_user_id <= ANONYMOUS || !check_link_hash($hash, 'adminhelper_restore_notify_' . $restore_user_id))
            {
                trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sql = 'SELECT user_id, user_type, user_email
                FROM ' . USERS_TABLE . '
                WHERE user_id = ' . $restore_user_id;
            $result = $db->sql_query($sql);
            $restore_user = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$restore_user || (int) $restore_user['user_type'] === USER_IGNORE)
            {
                trigger_error($user->lang('ACP_ADMINHELPER_RESTORE_NOTIFY_INVALID_USER') . adm_back_link($this->u_action), E_USER_WARNING);
            }

            $sql = 'UPDATE ' . USERS_TABLE . '
                SET user_notify = 1,
                    user_notify_type = CASE
                        WHEN user_notify_type = ' . NOTIFY_IM . ' THEN ' . NOTIFY_BOTH . '
                        WHEN user_notify_type IN (' . NOTIFY_EMAIL . ', ' . NOTIFY_BOTH . ') THEN user_notify_type
                        ELSE ' . NOTIFY_EMAIL . '
                    END
                WHERE user_id = ' . $restore_user_id;
            $db->sql_query($sql);

            $missing_rows = [];
            $sql = 'SELECT nt.notification_type_name
                FROM ' . NOTIFICATION_TYPES_TABLE . ' nt
                LEFT JOIN ' . USER_NOTIFICATIONS_TABLE . " un
                    ON un.user_id = " . $restore_user_id . "
                        AND un.item_type = nt.notification_type_name
                        AND un.item_id = 0
                        AND un.method = 'notification.method.email'
                WHERE nt.notification_type_enabled = 1
                    AND un.user_id IS NULL";
            $result = $db->sql_query($sql);
            while ($row = $db->sql_fetchrow($result))
            {
                $missing_rows[] = [
                    'item_type' => (string) $row['notification_type_name'],
                    'item_id' => 0,
                    'user_id' => $restore_user_id,
                    'method' => 'notification.method.email',
                    'notify' => 1,
                ];
            }
            $db->sql_freeresult($result);

            if (!empty($missing_rows))
            {
                $db->sql_multi_insert(USER_NOTIFICATIONS_TABLE, $missing_rows);
            }

            $sql = 'UPDATE ' . USER_NOTIFICATIONS_TABLE . "
                SET notify = 1
                WHERE user_id = " . $restore_user_id . "
                    AND method = 'notification.method.email'";
            $db->sql_query($sql);

            // Remet les surveillances en etat "pretes a notifier".
            $sql = 'UPDATE ' . TOPICS_WATCH_TABLE . '
                SET notify_status = 0
                WHERE user_id = ' . $restore_user_id;
            $db->sql_query($sql);

            $sql = 'UPDATE ' . FORUMS_WATCH_TABLE . '
                SET notify_status = 0
                WHERE user_id = ' . $restore_user_id;
            $db->sql_query($sql);

            $log_data = [
                'user_id' => $restore_user_id,
                'user_email' => substr((string) $restore_user['user_email'], 0, 255),
                'unsubscribe_type' => 'forum_notify',
                'token_expires_at' => 0,
                'http_status' => 200,
                'event_status' => 'admin_restored',
                'request_method' => 'ADMIN',
                'request_ip' => substr((string) $request->server('REMOTE_ADDR', ''), 0, 40),
                'request_user_agent' => substr((string) $request->server('HTTP_USER_AGENT', ''), 0, 255),
                'logged_at' => time(),
            ];
            $sql = 'INSERT INTO ' . $log_table . ' ' . $db->sql_build_array('INSERT', $log_data);
            $db->sql_query($sql);

            trigger_error($user->lang('ACP_ADMINHELPER_RESTORE_NOTIFY_SUCCESS') . adm_back_link($this->u_action));
        }

        $sql = 'SELECT COUNT(user_id) AS total_members,
                       SUM(CASE WHEN user_allow_massemail = 1 THEN 1 ELSE 0 END) AS massmail_subscribed,
                       SUM(CASE WHEN user_notify = 1 AND user_notify_type IN (' . NOTIFY_EMAIL . ', ' . NOTIFY_BOTH . ') THEN 1 ELSE 0 END) AS forum_notify_subscribed
            FROM ' . USERS_TABLE . '
            WHERE user_id <> ' . ANONYMOUS . '
                AND user_type <> ' . USER_IGNORE;
        $result = $db->sql_query($sql);
        $counter_row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $total_members = (int) ($counter_row['total_members'] ?? 0);
        $massmail_subscribed = (int) ($counter_row['massmail_subscribed'] ?? 0);
        $forum_notify_subscribed = (int) ($counter_row['forum_notify_subscribed'] ?? 0);
        $massmail_unsubscribed = max(0, $total_members - $massmail_subscribed);
        $forum_notify_unsubscribed = max(0, $total_members - $forum_notify_subscribed);

        $sql = 'SELECT COUNT(log_id) AS total_logs
            FROM ' . $log_table;
        $result = $db->sql_query($sql);
        $total_logs = (int) $db->sql_fetchfield('total_logs');
        $db->sql_freeresult($result);

        $sql = 'SELECT log_id, user_id, user_email, unsubscribe_type, token_expires_at, http_status, event_status,
                       request_method, request_ip, request_user_agent, logged_at
            FROM ' . $log_table . '
            ORDER BY log_id DESC';
        $result = $db->sql_query_limit($sql, $per_page, $start);

        while ($row = $db->sql_fetchrow($result))
        {
            $status_key = 'ACP_ADMINHELPER_STATUS_' . strtoupper((string) $row['event_status']);
            $status_label = isset($user->lang[$status_key]) ? $user->lang($status_key) : (string) $row['event_status'];
            $unsubscribe_type = strtolower((string) ($row['unsubscribe_type'] ?? 'massmail'));
            $type_key = 'ACP_ADMINHELPER_TYPE_' . strtoupper($unsubscribe_type);
            $type_label = isset($user->lang[$type_key]) ? $user->lang($type_key) : $unsubscribe_type;
            $user_id = (int) $row['user_id'];
            $request_user_agent = trim((string) $row['request_user_agent']);

            $template->assign_block_vars('logs', [
                'LOG_ID' => (int) $row['log_id'],
                'LOGGED_AT' => ((int) $row['logged_at'] > 0) ? $user->format_date((int) $row['logged_at']) : '-',
                'TYPE_LABEL' => $type_label,
                'STATUS_LABEL' => $status_label,
                'USER_ID' => $user_id > 0 ? $user_id : '-',
                'U_USER_PROFILE' => $user_id > 0 ? append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=viewprofile&u=' . $user_id) : '',
                'USER_EMAIL' => (string) $row['user_email'] !== '' ? (string) $row['user_email'] : '-',
                'HTTP_STATUS' => (int) $row['http_status'] > 0 ? (int) $row['http_status'] : '-',
                'REQUEST_METHOD' => (string) $row['request_method'] !== '' ? (string) $row['request_method'] : '-',
                'REQUEST_IP' => (string) $row['request_ip'] !== '' ? (string) $row['request_ip'] : '-',
                'TOKEN_EXPIRES_AT' => ((int) $row['token_expires_at'] > 0) ? $user->format_date((int) $row['token_expires_at']) : '-',
                'USER_AGENT' => $request_user_agent !== '' ? $request_user_agent : '-',
                'USER_AGENT_SHORT' => (strlen($request_user_agent) > 80) ? substr($request_user_agent, 0, 80) . '...' : ($request_user_agent !== '' ? $request_user_agent : '-'),
                'CAN_RESTORE_NOTIFY' => $unsubscribe_type === 'forum_notify' && $user_id > ANONYMOUS,
                'U_RESTORE_NOTIFY' => ($unsubscribe_type === 'forum_notify' && $user_id > ANONYMOUS)
                    ? append_sid($this->u_action, 'action=restore_notify&uid=' . $user_id . '&hash=' . generate_link_hash('adminhelper_restore_notify_' . $user_id))
                    : '',
                'RESTORE_NOTIFY_LABEL' => $user->lang('ACP_ADMINHELPER_ACTION_RESTORE_NOTIFY'),
                'RESTORE_NOTIFY_CONFIRM' => $user->lang('ACP_ADMINHELPER_RESTORE_NOTIFY_CONFIRM'),
                'ACTION_NONE_LABEL' => $user->lang('ACP_ADMINHELPER_ACTION_NONE'),
            ]);
        }
        $db->sql_freeresult($result);

        $pagination = $phpbb_container->get('pagination');
        $pagination->generate_template_pagination($this->u_action, 'pagination', 'start', $total_logs, $per_page, $start);

        $template->assign_vars([
            'U_ACTION' => $this->u_action,
            'START' => $start,
            'TOTAL_MEMBERS_COUNTED' => $total_members,
            'MASSMAIL_SUBSCRIBED' => $massmail_subscribed,
            'MASSMAIL_UNSUBSCRIBED' => $massmail_unsubscribed,
            'FORUM_NOTIFY_SUBSCRIBED' => $forum_notify_subscribed,
            'FORUM_NOTIFY_UNSUBSCRIBED' => $forum_notify_unsubscribed,
            'TOTAL_LOGS' => $total_logs,
            'PAGINATION' => '',
            'S_ON_PAGE' => $pagination->on_page($total_logs, $per_page, $start),
        ]);
    }
}
