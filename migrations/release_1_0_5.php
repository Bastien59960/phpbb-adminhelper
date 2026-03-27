<?php
/**
 * Admin Helper Extension - Migration v1.0.5
 * Store detected AI provider label for attachments.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_5 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\adminhelper\migrations\release_1_0_4'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'adminhelper_attachment_ai' => [
                    'ai_provider' => ['VCHAR:32', ''],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_columns' => [
                $this->table_prefix . 'adminhelper_attachment_ai' => [
                    'ai_provider',
                ],
            ],
        ];
    }
}
