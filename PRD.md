# PRD — bastien59960/adminhelper

**Dernière mise à jour :** 2026-03-15
**Extension :** `ext/bastien59960/adminhelper`
**Version courante :** 1.0.2

---

## Objectif

Extension de confort pour l'administration du forum : durcissement des emails de masse, conformité RFC 8058 désinscription, logs d'audit, correctif du cron_lock phpBB, et amélioration de la recherche de posts par auteur.

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

## Migrations

| Fichier | Contenu |
|---|---|
| `release_1_0_1.php` | Table `adminhelper_unsubscribe_log`, configs, module ACP |
| `release_1_0_2.php` | Compteurs UCP, stats emails réactions |

Aucune migration n'est nécessaire pour la fonctionnalité de recherche (2026-03-15) : tout est traité en PHP/template, sans schéma DB supplémentaire.

---

## Dépendances internes

- Utilise les événements phpBB : `core.common`, `core.search_modify_rowset`, `core.search_modify_tpl_ary`, `core.acp_users_modify_sql_query`, `core.submit_post_modify_sql_data`.
- Surcharge du service `cron.event_listener` pour le correctif lock.
- Pas de dépendance externe (pas d'API tierce).

---

## Fichiers clés

```
event/listener.php                          — abonnements événements phpBB
event/safe_cron_runner_listener.php         — surcharge cron lock fix
acp/main_module.php                         — contrôleur ACP logs désinscriptions
migrations/release_1_0_1.php               — schéma initial
migrations/release_1_0_2.php               — stats UCP
tools/cron_watchdog.sh                      — watchdog lock orphelins
styles/prosilver/template/event/
  search_results_header_before.html         — CSS injection
  search_results_content_after.html         — PJ non-inline rendu
```
