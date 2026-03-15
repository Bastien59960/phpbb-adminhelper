# Plan d'implémentation — Notes de modération (v1.0.3)

**Date :** 2026-03-15
**Fonctionnalité :** Notes de modération sur les posts, page "Posts à modérer", bords rouges danger.

---

## Vue d'ensemble

Chaque post peut recevoir une note interne (1 seule par post). Seuls les modérateurs/admins voient le bouton d'ajout, la note affichée, et le lien "Posts à modérer" dans le menu Accès rapide.

---

## Étape 1 — Migration DB (`migrations/release_1_0_3.php`)

Créer la table `phpbb3_adminhelper_mod_notes` :

```php
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
            'post_id'  => ['UNIQUE', 'post_id'],   // 1 note max par post
            'forum_id' => ['INDEX', 'forum_id'],
        ],
    ],
],
```

`effectively_installed()` : vérifier `$this->db->sql_table_exists($this->table_prefix . 'adminhelper_mod_notes')`.
`revert_schema()` : `drop_tables`.

---

## Étape 2 — Langue

### `language/fr/adminhelper.php` (ajouter dans le array existant)

```php
'ADMINHELPER_MOD_NOTE_BTN'        => 'Inclure une note de modération',
'ADMINHELPER_MOD_NOTE_SAVE'       => 'Enregistrer la note',
'ADMINHELPER_MOD_NOTE_DELETE'     => 'Supprimer la note',
'ADMINHELPER_MOD_NOTE_PLACEHOLDER'=> 'Note interne (visible uniquement par les modérateurs)...',
'ADMINHELPER_MOD_NOTE_BY'         => 'Note de %s le %s',
'ADMINHELPER_MOD_NOTE_SAVED'      => 'Note enregistrée.',
'ADMINHELPER_MOD_NOTE_DELETED'    => 'Note supprimée.',
'ADMINHELPER_MOD_NOTE_DENIED'     => 'Action non autorisée.',
'ADMINHELPER_MOD_NOTES_TITLE'     => 'Posts à modérer',
'ADMINHELPER_MOD_NOTES_EXPLAIN'   => 'Liste de tous les posts ayant une note de modération.',
'ADMINHELPER_MOD_NOTES_EMPTY'     => 'Aucun post avec note de modération.',
'ADMINHELPER_MOD_NOTES_QUICKLINK' => 'Posts à modérer',
```

### `language/en/adminhelper.php` (même clés, traduction anglaise)

---

## Étape 3 — Services et routing

### `config/services.yml` — ajouter le controller

```yaml
bastien59960.adminhelper.mod_notes:
    class: bastien59960\adminhelper\controller\mod_notes_controller
    arguments:
        - '@dbal.conn'
        - '@auth'
        - '@user'
        - '@template'
        - '@language'
        - '@request'
        - '@helper'               # phpbb\controller\helper
        - '%tables.adminhelper_mod_notes%'
        - '%tables.phpbb_posts%'  # pour JOIN forum_id
```

Ajouter dans le listener existant : `@auth` et `@user` (non encore injectés).

### `config/parameters.yml` — ajouter la table

```yaml
tables.adminhelper_mod_notes: '%core.table_prefix%adminhelper_mod_notes'
```

### `config/routing.yml` (nouveau fichier)

```yaml
adminhelper_mod_notes_list:
    path: /adminhelper/mod-notes
    defaults: { _controller: bastien59960.adminhelper.mod_notes:list_notes }
    methods: [GET]

adminhelper_mod_notes_save:
    path: /adminhelper/mod-notes/save
    defaults: { _controller: bastien59960.adminhelper.mod_notes:save }
    methods: [POST]

adminhelper_mod_notes_delete:
    path: /adminhelper/mod-notes/delete/{note_id}
    defaults: { _controller: bastien59960.adminhelper.mod_notes:delete, note_id: 0 }
    methods: [POST]
```

---

## Étape 4 — Listener (`event/listener.php`)

### 4a. Ajouter `@auth` et `@user` dans le constructeur

Mettre à jour `services.yml` et `__construct()` :
```php
public function __construct(
    \phpbb\db\driver\driver_interface $db,
    \phpbb\request\request $request,
    \phpbb\template\template $template,
    \phpbb\language\language $language,
    \phpbb\auth\auth $auth,
    \phpbb\user $user,
    ?\phpbb\config\config $config = null,
    string $mod_notes_table = ''
)
```

### 4b. Nouveaux abonnements

```php
'core.page_header'                => ['on_page_header', 0],
'core.viewtopic_post_row_after'   => ['on_viewtopic_post_row', 0],
```

### 4c. `on_page_header($event)`

```php
$is_mod = $this->auth->acl_getf_global('m_edit') || $this->auth->acl_get('a_');
$this->template->assign_vars([
    'ADMINHELPER_IS_MOD_OR_ADMIN' => $is_mod,
]);
```

### 4d. `on_viewtopic_post_row($event)`

```php
$post_id  = (int) $event['row']['post_id'];
$forum_id = (int) $event['row']['forum_id'];

$can_note = $this->auth->acl_get('m_edit', $forum_id)
         || $this->auth->acl_get('a_');

if (!$can_note) {
    return;
}

// Charger la note existante
$sql = 'SELECT n.*, u.username
        FROM ' . $this->mod_notes_table . ' n
        LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = n.note_author_id
        WHERE n.post_id = ' . $post_id;
$result = $this->db->sql_query($sql);
$note   = $this->db->sql_fetchrow($result);
$this->db->sql_freeresult($result);

// Générer les URLs (via helper ou manuellement)
$root_path   = generate_board_url() . '/app.php';
$u_save      = append_sid($root_path . '/adminhelper/mod-notes/save');
$u_delete    = $note ? append_sid($root_path . '/adminhelper/mod-notes/delete/' . (int) $note['note_id']) : '';

$this->template->alter_block_array('postrow', [
    'ADMINHELPER_CAN_MOD_NOTE'  => true,
    'ADMINHELPER_HAS_NOTE'      => (bool) $note,
    'ADMINHELPER_NOTE_TEXT'     => $note ? $note['note_text'] : '',
    'ADMINHELPER_NOTE_BY'       => $note
        ? $this->language->lang('ADMINHELPER_MOD_NOTE_BY', $note['username'], $this->user->format_date($note['note_created']))
        : '',
    'ADMINHELPER_NOTE_ID'       => $note ? (int) $note['note_id'] : 0,
    'ADMINHELPER_U_SAVE_NOTE'   => $u_save,
    'ADMINHELPER_U_DELETE_NOTE' => $u_delete,
    'ADMINHELPER_POST_ID_NOTE'  => $post_id,
], true, 'change');

// Charger la langue si pas encore fait
$this->load_language();
```

> **Note technique :** `alter_block_array('postrow', $data, true, 'change')` modifie la DERNIÈRE itération du bloc `postrow` en cours de construction. Comme l'événement se déclenche séquentiellement post par post AVANT que Twig rende le template (rendu différé en `page_footer()`), le dernier élément correspond toujours au post courant.

---

## Étape 5 — Controller (`controller/mod_notes_controller.php`)

### `list_notes()`

- Vérifier `$this->auth->acl_getf_global('m_edit') || $this->auth->acl_get('a_')` → 403 sinon.
- Requête : `SELECT n.*, p.post_subject, p.poster_id, u.username AS poster_name, f.forum_name, u2.username AS note_author FROM adminhelper_mod_notes n JOIN phpbb3_posts p ON p.post_id = n.post_id JOIN phpbb3_forums f ON f.forum_id = n.forum_id JOIN phpbb3_users u ON u.user_id = p.poster_id LEFT JOIN phpbb3_users u2 ON u2.user_id = n.note_author_id ORDER BY n.note_created DESC`.
- Assigner `template->assign_block_vars('mod_notes', [...])`.
- Rendre `adminhelper_mod_notes.html`.

### `save()`

- Récupérer `post_id` et `note_text` du POST.
- Vérifier `check_form_key('adminhelper_mod_note')` → erreur si invalide.
- Charger le `forum_id` depuis `phpbb3_posts WHERE post_id = $post_id`.
- Vérifier `$auth->acl_get('m_edit', $forum_id)` → 403 sinon.
- `UPSERT` (INSERT ... ON DUPLICATE KEY UPDATE) dans `adminhelper_mod_notes`.
- Redirect vers `viewtopic.php?p={post_id}#p{post_id}` avec message flash.

### `delete()`

- Vérifier `check_form_key('adminhelper_mod_note_delete')`.
- Charger la note, vérifier `$auth->acl_get('m_edit', $note['forum_id'])`.
- `DELETE FROM adminhelper_mod_notes WHERE note_id = $note_id`.
- Redirect vers referer ou viewtopic.

---

## Étape 6 — Templates

### `event/overall_header_head_append.html` (nouveau)

CSS global injecté dans `<head>` sur toutes les pages :

```css
<style>
/* Admin Helper — boutons danger (bord rouge) */
.post-buttons a[href*="mode=edit"],
.adminhelper-note-btn {
    outline: 2px solid #c00;
    outline-offset: 1px;
    border-radius: 2px;
}
/* Admin Helper — éditeur note modération */
.adminhelper-note-editor {
    display: none;
    margin-top: 6px;
    padding: 8px;
    background: #fff8e1;
    border-left: 3px solid #e67e22;
    border-radius: 2px;
}
.adminhelper-note-editor textarea {
    width: 100%;
    min-height: 60px;
    font-size: .9em;
    box-sizing: border-box;
    border: 1px solid #ccc;
    border-radius: 2px;
    padding: 4px;
    resize: vertical;
}
/* Admin Helper — affichage note existante */
.adminhelper-mod-note-box {
    margin: 8px 0 0;
    padding: 6px 10px;
    background: #fff3cd;
    border-left: 3px solid #e0a800;
    border-radius: 2px;
    font-size: .9em;
}
.adminhelper-mod-note-meta {
    color: #888;
    font-size: .85em;
    margin-bottom: 4px;
}
.adminhelper-mod-note-text {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
```

> **Choix `outline` vs `border`** : les boutons `.button-icon-only` ont déjà `border` avec styles prosilver. Utiliser `outline` évite de casser le layout (outline ne prend pas de place dans le flux).

### `event/viewtopic_body_post_buttons_after.html` (nouveau)

```twig
{% if postrow.ADMINHELPER_CAN_MOD_NOTE is defined and postrow.ADMINHELPER_CAN_MOD_NOTE %}
<li>
    <button
        type="button"
        class="button button-icon-only adminhelper-note-btn"
        title="{{ lang('ADMINHELPER_MOD_NOTE_BTN') }}"
        onclick="
            var ed = document.getElementById('adminhelper-note-ed-{{ postrow.ADMINHELPER_POST_ID_NOTE }}');
            ed.style.display = ed.style.display === 'none' || ed.style.display === '' ? 'block' : 'none';
        "
        aria-label="{{ lang('ADMINHELPER_MOD_NOTE_BTN') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" style="vertical-align:middle">
            <rect x="1" y="1" width="10" height="14" rx="1" ry="1" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <line x1="3" y1="5" x2="9" y2="5" stroke="currentColor" stroke-width="1.2"/>
            <line x1="3" y1="8" x2="9" y2="8" stroke="currentColor" stroke-width="1.2"/>
            <line x1="3" y1="11" x2="7" y2="11" stroke="currentColor" stroke-width="1.2"/>
            <path d="M11 9 l3-3 1.5 1.5 -3 3z M11 9 l-1 3 3-1z" fill="currentColor"/>
        </svg>
        <span class="sr-only">{{ lang('ADMINHELPER_MOD_NOTE_BTN') }}</span>
    </button>
</li>
{% endif %}
```

### `event/viewtopic_body_postrow_post_content_footer.html` (nouveau)

```twig
{% if postrow.ADMINHELPER_CAN_MOD_NOTE is defined and postrow.ADMINHELPER_CAN_MOD_NOTE %}

    {# Note existante #}
    {% if postrow.ADMINHELPER_HAS_NOTE %}
    <div class="adminhelper-mod-note-box">
        <div class="adminhelper-mod-note-meta">
            <i class="icon fa-lock fa-fw" aria-hidden="true"></i>
            {{ postrow.ADMINHELPER_NOTE_BY }}
        </div>
        <div class="adminhelper-mod-note-text">{{ postrow.ADMINHELPER_NOTE_TEXT | e }}</div>
        <form method="post" action="{{ postrow.ADMINHELPER_U_DELETE_NOTE }}" style="margin-top:6px">
            {{ S_FORM_TOKEN.adminhelper_mod_note_delete | raw }}
            <button type="submit" class="button button-secondary" style="font-size:.85em">
                <i class="icon fa-trash fa-fw" aria-hidden="true"></i>
                {{ lang('ADMINHELPER_MOD_NOTE_DELETE') }}
            </button>
        </form>
    </div>
    {% endif %}

    {# Éditeur inline #}
    <div class="adminhelper-note-editor" id="adminhelper-note-ed-{{ postrow.ADMINHELPER_POST_ID_NOTE }}">
        <form method="post" action="{{ postrow.ADMINHELPER_U_SAVE_NOTE }}">
            {{ S_FORM_TOKEN.adminhelper_mod_note | raw }}
            <input type="hidden" name="post_id" value="{{ postrow.ADMINHELPER_POST_ID_NOTE }}">
            <textarea name="note_text" placeholder="{{ lang('ADMINHELPER_MOD_NOTE_PLACEHOLDER') }}">{{ postrow.ADMINHELPER_NOTE_TEXT | e }}</textarea>
            <button type="submit" class="button button-secondary" style="margin-top:4px;font-size:.85em">
                <i class="icon fa-save fa-fw" aria-hidden="true"></i>
                {{ lang('ADMINHELPER_MOD_NOTE_SAVE') }}
            </button>
        </form>
    </div>

{% endif %}
```

> **Note :** `S_FORM_TOKEN.adminhelper_mod_note` est la syntaxe Twig pour les form keys phpBB. Le controller appellera `add_form_key('adminhelper_mod_note')` avant d'afficher le template.
> Alternativement (si phpBB ne supporte pas cette syntaxe Twig), utiliser `{S_FORM_TOKEN}` ou générer le token manuellement dans le listener.

> **Précision sur la génération du form token :** phpBB injecte les form tokens via `add_form_key()` en PHP, qui appelle `$template->assign_vars(['S_FORM_TOKEN' => ...])`. Pour des form keys nommés (non-default), il faut appeler `add_form_key('adminhelper_mod_note')` dans le listener lors de `core.viewtopic_post_row_after`, ou dans un `core.page_footer` event. En pratique, utiliser l'helper phpBB standard : `add_form_key('adminhelper_mod_note')` dans le listener `on_page_header`, puis `{S_FORM_TOKEN}` dans le template.

### `event/navbar_header_quick_links_after.html` (nouveau)

```twig
{% if S_USER_LOGGED_IN and ADMINHELPER_IS_MOD_OR_ADMIN is defined and ADMINHELPER_IS_MOD_OR_ADMIN %}
<li class="separator"></li>
<li>
    <a href="{{ helper.route('adminhelper_mod_notes_list') }}" role="menuitem">
        <i class="icon fa-flag fa-fw icon-red" aria-hidden="true"></i>
        <span>{{ lang('ADMINHELPER_MOD_NOTES_QUICKLINK') }}</span>
    </a>
</li>
{% endif %}
```

> **Note :** `helper.route()` est la fonction Twig phpBB qui génère une URL à partir du nom de route routing.yml. Sinon utiliser `append_sid(generate_board_url() . '/app.php/adminhelper/mod-notes')` en PHP dans `on_page_header` pour injecter `ADMINHELPER_U_MOD_NOTES`.

### `styles/prosilver/template/adminhelper_mod_notes.html` (nouveau)

Page liste : tableau avec colonnes Date note, Auteur note, Sujet post, Auteur post, Forum, Extrait note, Actions.
Utilise `<!-- BEGIN mod_notes -->...<!-- END mod_notes -->` phpBB template loop.

---

## Étape 7 — CSS résumé (bords rouges)

Cibles CSS dans `overall_header_head_append.html` :

| Sélecteur | Raison |
|---|---|
| `.post-buttons a[href*="mode=edit"]` | Bouton Éditer (fa-pencil, confondu avec Citer) |
| `.adminhelper-note-btn` | Bouton Note modération (action sensible) |

Utiliser `outline: 2px solid #c00` pour ne pas perturber le box model des boutons existants.

---

## Étape 8 — Ordre d'implémentation

1. `migrations/release_1_0_3.php` — DB schema
2. `config/parameters.yml` — nom de table
3. `config/routing.yml` — routes
4. `config/services.yml` — controller + update listener args
5. `event/listener.php` — ajout `@auth`, `@user`, `on_page_header`, `on_viewtopic_post_row`
6. `controller/mod_notes_controller.php` — CRUD + list page
7. Templates event × 4 + page liste
8. Fichiers langue fr + en
9. `php bin/phpbbcli.php extension:disable/enable bastien59960/adminhelper` (applique migration)
10. `rm -rf /var/www/forum/cache/production/*`
11. Tests : ajout note, édition, suppression, page liste, CSRF, droits modérateur

---

## Points d'attention

- **`alter_block_array` timing** : l'event `core.viewtopic_post_row_after` se déclenche DANS la boucle PHP de viewtopic.php, avant le rendu Twig (différé à `page_footer()`). `alter_block_array('postrow', ..., true, 'change')` modifie le dernier élément = le post courant. ✓
- **Form key unique par post** : si on utilise `add_form_key('adminhelper_mod_note')`, le même token couvre tous les formulaires de la page. Acceptable (le `post_id` dans le champ caché identifie le post cible).
- **1 note par post** : la contrainte UNIQUE sur `post_id` en DB empêche les doublons. L'enregistrement utilise `INSERT ... ON DUPLICATE KEY UPDATE`.
- **UPSERT MariaDB** : `INSERT INTO ... ON DUPLICATE KEY UPDATE note_text = VALUES(note_text), note_author_id = VALUES(note_author_id), note_created = VALUES(note_created)`.
- **Permissions granulaires** : vérifier `m_edit` sur le `forum_id` du post spécifique (pas global) pour `save()` et `delete()`. La page liste vérifie `acl_getf_global('m_edit')` (moderateur dans au moins 1 forum).
- **CSS `mode=edit`** : l'URL `U_EDIT` contient `mode=edit` (via `posting.php?mode=edit&...`). Le sélecteur `a[href*="mode=edit"]` est stable dans phpBB 3.3.
