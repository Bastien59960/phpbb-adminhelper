<?php
/**
 * Admin Helper Extension - Migration v1.0.3
 * Notes de modération sur les posts.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\bastien59960\adminhelper\migrations\release_1_0_2'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists(
			$this->table_prefix . 'adminhelper_mod_notes'
		);
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'adminhelper_mod_notes' => [
					'COLUMNS' => [
						'note_id'        => ['UINT', null, 'auto_increment'],
						'post_id'        => ['UINT', 0],
						'forum_id'       => ['UINT', 0],
						'note_text'      => ['TEXT_UNI', ''],
						'note_author_id' => ['UINT', 0],
						'note_created'   => ['UINT:11', 0],
					],
					'PRIMARY_KEY' => 'note_id',
					'KEYS' => [
						'post_id'  => ['UNIQUE', 'post_id'],
						'forum_id' => ['INDEX', 'forum_id'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'adminhelper_mod_notes',
			],
		];
	}

	public function update_data()
	{
		return [];
	}

	public function revert_data()
	{
		return [];
	}
}
