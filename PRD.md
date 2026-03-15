# PRD — bastien59960/adminhelper

**Dernière mise à jour :** 2026-03-15
**Extension :** `ext/bastien59960/adminhelper`
**Version courante :** 1.0.3 (en développement)

---

## Objectif

Extension de confort pour l'administration du forum : durcissement des emails de masse, conformité RFC 8058 désinscription, logs d'audit, correctif du cron_lock phpBB, amélioration de la recherche de posts par auteur, et notes de modération sur les posts.

---

## Fonctionnalités

### 1. Recherche membre par email (ACP)

`ACP > Utilisateurs et groupes > Gérer les utilisateurs`

Ajout d'un champ de recherche par adresse email pour retrouver un membre quand son pseudo est inconnu. Hook sur `core.common` (avant `IN_ADMIN`).

---

### 2. Emails de masse ACP — durcissement

`ACP > Général > Communication client > Email`

- Contenu HTML optionnel avec fallback plain-text automatique.
- Pied de page de désinscription inséré automatiquement.
- Mode envoi en file sécurisé pour les grands volumes.
- En-têtes RFC 8058 ajoutés : `List-Unsubscribe` + `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
- Liens signés HMAC à expiration par destinataire.
- Scopes distincts : `massmail`, `forum_notify`.

---

### 3. Gestion des désinscriptions (RFC 8058)

- Interception des requêtes signées sur l'entrypoint forum.
- Page de confirmation (GET) + action one-click (POST).
- Vérification signature / expiry / mapping utilisateur avant application.
- Codes HTTP explicites : 200, 400, 403, 410.
- Suivi des changements de préférence UCP (notification désactivée manuellement depuis le panneau utilisateur).

---

### 4. Logs de désinscriptions (ACP)

`ACP > Extensions > Admin Helper > Logs désinscriptions`

- Compteurs globaux massmail / notifications forum.
- Journal détaillé : date, type, statut, membre, email, code HTTP, méthode, IP, expiry token, user-agent.
- Action admin : restaurer les notifications forum d'un membre.
- Action admin : purger les vieilles notifications non lues (avec prévisualisation du volume).
- Suppression sélective / bulk des entrées de log.

**Table :** `phpbb3_adminhelper_unsubscribe_log`

---

### 5. Correctif cron_lock phpBB (2026-03-06)

**Problème :** `shell_exec()` dans `stats/listener.php` acquiert le mutex POSIX `popen_list_mutex` (PI mutex glibc). Deux workers Apache simultanés → blocage en cascade 13+ minutes, le cron ne se libère plus.

**Fix 1 — Safe cron runner :**
`event/safe_cron_runner_listener.php` surcharge `cron.event_listener` avec try-finally → libère le lock même en cas d'exception. Priorité d'événement sur phpBB core.

**Fix 2 — Watchdog :**
`tools/cron_watchdog.sh` lancé toutes les 5 minutes via crontab. Libère les locks orphelins de plus de 300 s.
Log : `/var/log/phpbb_cron_watchdog.log`

**Fix 3 :** Suppression du `shell_exec` dans `get_cached_hostname` → retourne null si cache miss → vérification rDNS différée en async.

---

### 6. Affichage complet des posts sur recherche par auteur (2026-03-15)

`search.php?author_id=N&sr=posts`

Quand on recherche les posts d'un auteur, phpBB tronque les messages longs (flag `display_text_only=true`) et supprime les pièces jointes non-inline de la vue template. Cette fonctionnalité corrige les deux problèmes :

**Corps complet :**
Hook `core.search_modify_rowset` — si `display_text_only=true`, re-fetch `post_text` / `bbcode_uid` / `bbcode_bitfield` depuis `phpbb3_posts` et force `display_text_only=false`. Le BBCode est rendu normalement (images inline incluses).

**Pièces jointes non-inline :**
phpBB fait `unset($attachments[$post_id])` après `parse_attachments()` dans `search.php`, avant l'event `core.search_modify_tpl_ary`. Les PJ sont donc perdues au moment du template.
Fix : stocker les lignes brutes DB des PJ dans une propriété listener lors de `core.search_modify_rowset` (avant la destruction), les consommer dans `core.search_modify_tpl_ary`.

**Détection inline :** si `attach_id` apparaît dans l'URL du HTML rendu du message (`id=<N>`), la PJ est déjà affichée inline → ignorée.

**Rendu des PJ non-inline :**
- Image avec miniature → miniature cliquable + lien téléchargement.
- Image sans miniature → image directe (max 72px height).
- Autre type → icône FontAwesome 4 + lien téléchargement + taille du fichier.

**Template events créés :**
- `search_results_header_before.html` — injection CSS `.adminhelper-search-attaches` / `.adminhelper-attach-item`.
- `search_results_content_after.html` — rendu conditionnel de `ADMINHELPER_SEARCH_ATTACHES`.

---

### 7. Notes de modération sur les posts (2026-03-15)

Fonctionnalité réservée aux modérateurs et administrateurs permettant d'attacher une note interne à n'importe quel post du forum. Ces notes sont invisibles des membres ordinaires.

#### 7a. Icône dans la barre d'actions du post

Un bouton SVG "bloc-notes + crayon" s'ajoute à la barre `Éditer / Supprimer / Rapporter / Avertir / Info / Citer`, visible uniquement si l'utilisateur a le droit `m_edit` sur le forum du post (ou `a_` global admin). Cliquer dessus affiche/masque un éditeur inline (textarea + bouton Enregistrer).

Le bouton "note de modération" est entouré d'un **bord rouge 2 px** (signal de danger). L'icône "Éditer le post" (fa-pencil, souvent confondue avec "Citer") reçoit le même traitement.

#### 7b. Affichage de la note existante

Si une note existe sur un post, une zone `adminhelper-mod-note-box` s'affiche en bas du post (sous la signature), visible uniquement aux modérateurs/admins. Elle contient :
- le texte de la note,
- l'auteur de la note et la date,
- un bouton "Supprimer la note" (POST sécurisé avec form key).

Un seul note par post (UNIQUE sur `post_id`). L'enregistrement d'une nouvelle note remplace l'existante.

#### 7c. Page "Consulter les posts à modérer"

Accessible via le **menu Accès rapide** (dropdown "hamburger") — sous l'entrée "Brouillons" ajoutée par geoexplo. Lien visible uniquement si `ADMINHELPER_IS_MOD_OR_ADMIN = true` (injecté en `core.page_header`).

Page listée à `/app.php/adminhelper/mod-notes` :
- Tableau de tous les posts ayant une note : date note, auteur note, titre post, auteur post, forum, extrait de note, lien direct, bouton "Supprimer".
- Accessible uniquement aux modérateurs/admins (vérification `$auth->acl_getf_global('m_edit')` ou admin).

#### 7d. Sécurité

- Contrôle d'accès : `$auth->acl_get('m_edit', $forum_id)` pour chaque action sur un post.
- CSRF : formulaires protégés par `add_form_key()` / `check_form_key()` phpBB.
- Suppression : vérification que la note appartient bien au forum sur lequel l'utilisateur est modérateur.

**Table :** `phpbb3_adminhelper_mod_notes`
```
note_id       UINT AUTO_INCREMENT PK
post_id       UINT NOT NULL UNIQUE
forum_id      UINT NOT NULL
note_text     TEXT NOT NULL
note_author_id UINT NOT NULL
note_created  UINT NOT NULL
```

**Nouveaux événements phpBB :**
- `core.page_header` → injection de `ADMINHELPER_IS_MOD_OR_ADMIN`
- `core.viewtopic_post_row_after` → chargement de la note du post, injection via `alter_block_array`

**Nouveaux fichiers template :**
- `event/viewtopic_body_post_buttons_after.html` — bouton icône note
- `event/viewtopic_body_postrow_post_content_footer.html` — affichage note + formulaire éditeur
- `event/navbar_header_quick_links_after.html` — lien "Posts à modérer"
- `event/overall_header_head_append.html` — CSS global (bords rouges + styles note)
- `adminhelper_mod_notes.html` — page liste complète

---

## Migrations

| Fichier | Contenu |
|---|---|
| `release_1_0_1.php` | Table `adminhelper_unsubscribe_log`, configs, module ACP |
| `release_1_0_2.php` | Compteurs UCP, stats emails réactions |
| `release_1_0_3.php` | Table `adminhelper_mod_notes` (notes de modération) |

Aucune migration n'est nécessaire pour la fonctionnalité de recherche (2026-03-15) : tout est traité en PHP/template, sans schéma DB supplémentaire.

---

## Dépendances internes

- Utilise les événements phpBB : `core.common`, `core.page_header`, `core.search_modify_rowset`, `core.search_modify_tpl_ary`, `core.acp_users_modify_sql_query`, `core.submit_post_modify_sql_data`, `core.viewtopic_post_row_after`.
- Surcharge du service `cron.event_listener` pour le correctif lock.
- Pas de dépendance externe (pas d'API tierce).

---

## Fichiers clés

```
event/listener.php                          — abonnements événements phpBB
event/safe_cron_runner_listener.php         — surcharge cron lock fix
acp/main_module.php                         — contrôleur ACP logs désinscriptions
controller/mod_notes_controller.php         — CRUD notes + page liste (v1.0.3)
migrations/release_1_0_1.php               — schéma initial
migrations/release_1_0_2.php               — stats UCP
migrations/release_1_0_3.php               — table adminhelper_mod_notes
tools/cron_watchdog.sh                      — watchdog lock orphelins
config/routing.yml                          — routes controller mod_notes
styles/prosilver/template/event/
  search_results_header_before.html         — CSS injection (search)
  search_results_content_after.html         — PJ non-inline rendu
  overall_header_head_append.html           — CSS global mod-notes + bords danger
  viewtopic_body_post_buttons_after.html    — bouton icône note modération
  viewtopic_body_postrow_post_content_footer.html — affichage note + éditeur
  navbar_header_quick_links_after.html      — lien "Posts à modérer" (mods only)
styles/prosilver/template/
  adminhelper_mod_notes.html               — page liste tous les posts avec note
```
