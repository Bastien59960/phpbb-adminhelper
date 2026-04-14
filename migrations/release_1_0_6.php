<?php
/**
 * Admin Helper Extension - Migration v1.0.6
 * Forum gate: per-forum access control by post count and guest visibility.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\migrations;

class release_1_0_6 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\adminhelper\migrations\release_1_0_5'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'adminhelper_forum_gate' => [
                    'COLUMNS' => [
                        'forum_id'         => ['UINT', 0],
                        'guest_hidden'     => ['BOOL', 0],
                        'min_posts_member' => ['UINT:8', 1],
                    ],
                    'PRIMARY_KEY' => 'forum_id',
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'adminhelper_forum_gate',
            ],
        ];
    }

    public function update_data()
    {
        return [
            // Master switch — désactivé par défaut, activé manuellement depuis l'ACP
            ['config.add', ['bastien59960_adminhelper_gate_enabled', 0]],
            // Forum de présentation (lien dans le message d'erreur membres)
            ['config.add', ['bastien59960_adminhelper_gate_presentation_forum_id', 14]],
            // Seuil membres par défaut quand aucune règle n'est trouvée dans la chaîne d'héritage
            ['config.add', ['bastien59960_adminhelper_gate_default_min_posts', 1]],
            // Masquer aux invités par défaut (0 = visible)
            ['config.add', ['bastien59960_adminhelper_gate_default_guest_hidden', 0]],
            // Insertion de l'exemption pour le forum de présentation (id=14)
            ['custom', [[$this, 'insert_presentation_forum_exemption']]],
            // Enregistrement du mode ACP
            ['module.add', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['forum_gate'],
            ]]],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.remove', ['bastien59960_adminhelper_gate_enabled']],
            ['config.remove', ['bastien59960_adminhelper_gate_presentation_forum_id']],
            ['config.remove', ['bastien59960_adminhelper_gate_default_min_posts']],
            ['config.remove', ['bastien59960_adminhelper_gate_default_guest_hidden']],
            ['module.remove', ['acp', 'ACP_ADMINHELPER_TITLE', [
                'module_basename' => '\bastien59960\adminhelper\acp\main_module',
                'modes' => ['forum_gate'],
            ]]],
        ];
    }

    /**
     * Insère l'exemption pour le forum de présentation (forum_id=14).
     * guest_hidden=0 (visible aux invités), min_posts_member=0 (aucune restriction membres).
     * Utilise INSERT IGNORE pour être idempotent si appelé plusieurs fois.
     */
    public function insert_presentation_forum_exemption()
    {
        $table = $this->table_prefix . 'adminhelper_forum_gate';
        $this->db->sql_query(
            'INSERT IGNORE INTO ' . $table . ' (forum_id, guest_hidden, min_posts_member) VALUES (14, 0, 0)'
        );
    }
}
