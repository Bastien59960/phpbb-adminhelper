<?php
/**
 * Admin Helper Extension - Migration v1.0.1
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_1 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log' => [
                    'COLUMNS' => [
                        'log_id' => ['UINT', null, 'auto_increment'],
                        'user_id' => ['UINT', 0],
                        'user_email' => ['VCHAR:255', ''],
                        'token_expires_at' => ['UINT:11', 0],
                        'http_status' => ['UINT:3', 0],
                        'event_status' => ['VCHAR:32', ''],
                        'request_method' => ['VCHAR:8', ''],
                        'request_ip' => ['VCHAR:40', ''],
                        'request_user_agent' => ['VCHAR:255', ''],
                        'logged_at' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'log_id',
                    'KEYS' => [
                        'user_id' => ['INDEX', 'user_id'],
                        'status' => ['INDEX', 'event_status'],
                        'logged_at' => ['INDEX', 'logged_at'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['adminhelper_unsub_log_enabled', 1]],
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_ADMINHELPER_TITLE']],
            ['module.add', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['unsubscribe_logs'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['adminhelper_unsub_log_enabled']],
            ['module.remove', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['unsubscribe_logs'],
            ]]],
            ['module.remove', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_ADMINHELPER_TITLE']],
        ];
    }
}
