<?php
/**
 * Admin Helper — Controller notes de modération
 *
 * Gère la sauvegarde, la suppression et la liste des notes de modération.
 * Accès réservé aux modérateurs (m_edit) et administrateurs.
 *
 * @package bastien59960/adminhelper
 * @license GPL-2.0-only
 */

namespace bastien59960\adminhelper\controller;

class mod_notes_controller
{
	protected $db;
	protected $auth;
	protected $user;
	protected $template;
	protected $language;
	protected $request;
	protected $helper;
	protected $mod_notes_table;
	protected $posts_table;
	protected $forums_table;
	protected $users_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\auth\auth $auth,
		\phpbb\user $user,
		\phpbb\template\template $template,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\controller\helper $helper,
		string $table_prefix = 'phpbb3_'
	) {
		$this->db              = $db;
		$this->auth            = $auth;
		$this->user            = $user;
		$this->template        = $template;
		$this->language        = $language;
		$this->request         = $request;
		$this->helper          = $helper;
		$this->mod_notes_table = $table_prefix . 'adminhelper_mod_notes';
		$this->posts_table     = $table_prefix . 'posts';
		$this->forums_table    = $table_prefix . 'forums';
		$this->users_table     = $table_prefix . 'users';
	}

	/**
	 * POST /adminhelper/mod-notes/save
	 * Crée ou met à jour la note d'un post (1 note max par post via UNIQUE).
	 */
	public function save()
	{
		$this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');

		if (!check_form_key('adminhelper_mod_note'))
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_INVALID'), E_USER_WARNING);
		}

		$post_id   = $this->request->variable('post_id', 0);
		$note_text = trim($this->request->variable('note_text', '', true));

		if ($post_id <= 0)
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		// Récupérer le forum_id du post pour vérifier les droits
		$sql    = 'SELECT forum_id FROM ' . $this->posts_table . ' WHERE post_id = ' . (int) $post_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		$forum_id = (int) $row['forum_id'];

		if (!$this->auth->acl_get('m_edit', $forum_id) && !$this->auth->acl_get('a_'))
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		$now = time();

		// UPSERT : crée ou remplace la note existante (UNIQUE sur post_id)
		$sql = 'INSERT INTO ' . $this->mod_notes_table . '
				(post_id, forum_id, note_text, note_author_id, note_created)
				VALUES (' . (int) $post_id . ', ' . $forum_id . ', \'' . $this->db->sql_escape($note_text) . '\', ' . (int) $this->user->data['user_id'] . ', ' . $now . ')
				ON DUPLICATE KEY UPDATE
					note_text      = \'' . $this->db->sql_escape($note_text) . '\',
					note_author_id = ' . (int) $this->user->data['user_id'] . ',
					note_created   = ' . $now;
		$this->db->sql_query($sql);

		// Redirige vers le post
		redirect(append_sid(generate_board_url() . '/viewtopic.php', 'p=' . (int) $post_id) . '#p' . (int) $post_id);
	}

	/**
	 * POST /adminhelper/mod-notes/delete/{note_id}
	 * Supprime une note de modération.
	 */
	public function delete($note_id)
	{
		$this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');

		$note_id = (int) $note_id;

		if (!check_form_key('adminhelper_mod_note'))
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_INVALID'), E_USER_WARNING);
		}

		if ($note_id <= 0)
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		// Charger la note pour vérifier les droits sur son forum
		$sql    = 'SELECT post_id, forum_id FROM ' . $this->mod_notes_table . ' WHERE note_id = ' . $note_id;
		$result = $this->db->sql_query($sql);
		$note   = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$note)
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		if (!$this->auth->acl_get('m_edit', (int) $note['forum_id']) && !$this->auth->acl_get('a_'))
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		$sql = 'DELETE FROM ' . $this->mod_notes_table . ' WHERE note_id = ' . $note_id;
		$this->db->sql_query($sql);

		// Redirige vers le post d'origine
		redirect(append_sid(generate_board_url() . '/viewtopic.php', 'p=' . (int) $note['post_id']) . '#p' . (int) $note['post_id']);
	}

	/**
	 * GET /adminhelper/mod-notes
	 * Liste tous les posts ayant une note de modération.
	 */
	public function list_notes()
	{
		$this->language->add_lang('info_acp_adminhelper', 'bastien59960/adminhelper');

		// Seuls les modérateurs globaux ou admins peuvent accéder à la liste
		if (!$this->auth->acl_getf_global('m_edit') && !$this->auth->acl_get('a_'))
		{
			trigger_error($this->language->lang('ADMINHELPER_MOD_NOTE_DENIED'), E_USER_WARNING);
		}

		$sql = 'SELECT
					n.note_id,
					n.post_id,
					n.note_text,
					n.note_created,
					p.post_subject,
					p.forum_id,
					p.poster_id,
					f.forum_name,
					u_poster.username  AS poster_name,
					u_author.username  AS note_author
				FROM ' . $this->mod_notes_table . ' n
				JOIN ' . $this->posts_table . ' p ON p.post_id = n.post_id
				JOIN ' . $this->forums_table . ' f ON f.forum_id = p.forum_id
				JOIN ' . $this->users_table . ' u_poster ON u_poster.user_id = p.poster_id
				LEFT JOIN ' . $this->users_table . ' u_author ON u_author.user_id = n.note_author_id
				ORDER BY n.note_created DESC';

		$result = $this->db->sql_query($sql);

		$has_notes = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			$has_notes = true;
			$post_subject = $row['post_subject'] ?: '#' . $row['post_id'];
			$u_post = append_sid(generate_board_url() . '/viewtopic.php', 'p=' . (int) $row['post_id']) . '#p' . (int) $row['post_id'];
			$u_delete = $this->helper->route('adminhelper_mod_notes_delete', ['note_id' => (int) $row['note_id']]);

			$this->template->assign_block_vars('mod_notes', [
				'NOTE_ID'      => (int) $row['note_id'],
				'NOTE_DATE'    => $this->user->format_date((int) $row['note_created']),
				'NOTE_AUTHOR'  => $row['note_author'] ?: '—',
				'NOTE_TEXT'    => $row['note_text'],
				'POST_ID'      => (int) $row['post_id'],
				'POST_SUBJECT' => $post_subject,
				'POST_AUTHOR'  => $row['poster_name'],
				'FORUM_NAME'   => $row['forum_name'],
				'U_POST'       => $u_post,
				'U_DELETE'     => $u_delete,
			]);
		}
		$this->db->sql_freeresult($result);

		add_form_key('adminhelper_mod_note');

		$this->template->assign_vars([
			'ADMINHELPER_MOD_NOTES_HAS_ROWS' => $has_notes,
		]);

		return $this->helper->render('adminhelper_mod_notes.html', $this->language->lang('ADMINHELPER_MOD_NOTES_TITLE'));
	}
}
