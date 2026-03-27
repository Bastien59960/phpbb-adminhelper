<?php
/**
 * Admin Helper Extension - Migration v1.0.4
 * Attachment AI declaration and scan storage.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_4 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\adminhelper\migrations\release_1_0_3'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'adminhelper_attachment_ai' => [
                    'COLUMNS' => [
                        'attach_id' => ['UINT', 0],
                        'post_id' => ['UINT', 0],
                        'user_id' => ['UINT', 0],
                        'is_ai_generated' => ['BOOL', 0],
                        'is_forced' => ['BOOL', 0],
                        'scan_status' => ['VCHAR:32', ''],
                        'detection_source' => ['VCHAR:32', ''],
                        'detection_reason' => ['VCHAR:64', ''],
                        'created_at' => ['UINT:11', 0],
                        'updated_at' => ['UINT:11', 0],
                    ],
                    'PRIMARY_KEY' => 'attach_id',
                    'KEYS' => [
                        'post_id' => ['INDEX', 'post_id'],
                        'user_id' => ['INDEX', 'user_id'],
                        'scan_status' => ['INDEX', 'scan_status'],
                        'is_ai_generated' => ['INDEX', 'is_ai_generated'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'adminhelper_attachment_ai',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['module.add', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['attachment_ai'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['module.remove', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['attachment_ai'],
            ]]],
        ];
    }
}
