<?php
/**
 * Admin Helper Extension - ACP module info
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\acp;

class main_info
{
    public function __construct()
    {
        global $phpbb_container;

        if (isset($phpbb_container))
        {
            $user = $phpbb_container->get('user');
            $user->add_lang_ext('bastien59960/adminhelper', 'acp/info_acp_adminhelper');
        }
    }

    public function module()
    {
        global $user;

        if (!isset($user->lang['ACP_ADMINHELPER_TITLE']))
        {
            $user->add_lang_ext('bastien59960/adminhelper', 'acp/info_acp_adminhelper');
        }

        return [
            'filename' => '\bastien59960\adminhelper\acp\main_module',
            'title' => 'ACP_ADMINHELPER_TITLE',
            'modes' => [
                'unsubscribe_logs' => [
                    'title' => 'ACP_ADMINHELPER_UNSUBSCRIBE_LOGS',
                    'auth' => 'ext_bastien59960/adminhelper && acl_a_board',
                    'cat' => ['ACP_ADMINHELPER_TITLE'],
                ],
                'attachment_ai' => [
                    'title' => 'ACP_ADMINHELPER_ATTACHMENT_AI',
                    'auth' => 'ext_bastien59960/adminhelper && acl_a_board',
                    'cat' => ['ACP_ADMINHELPER_TITLE'],
                ],
                'forum_gate' => [
                    'title' => 'ACP_FORUM_GATE_TITLE',
                    'auth' => 'ext_bastien59960/adminhelper && acl_a_board',
                    'cat' => ['ACP_ADMINHELPER_TITLE'],
                ],
            ],
        ];
    }
}
