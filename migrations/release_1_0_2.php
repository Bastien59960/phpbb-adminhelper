<?php
/**
 * Admin Helper Extension - Migration v1.0.2
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\adminhelper\migrations\release_1_0_1'];
    }

    public function update_schema()
    {
        return [
            'add_columns' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log' => [
                    'unsubscribe_type' => ['VCHAR:32', 'massmail'],
                ],
            ],
            'add_index' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log' => [
                    'unsubscribe_type' => ['unsubscribe_type'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_keys' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log' => [
                    'unsubscribe_type',
                ],
            ],
            'drop_columns' => [
                $this->table_prefix . 'adminhelper_unsubscribe_log' => [
                    'unsubscribe_type',
                ],
            ],
        ];
    }
}
