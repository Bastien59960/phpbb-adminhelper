# Bastien59960 Admin Helper — Extension phpBB 3.3+

[English](README.md)

Un ensemble d'utilitaires pratiques pour l'administration et la modération d'un forum phpBB : emails de masse sécurisés, conformité RFC 8058, logs d'audit, fiabilité du cron, amélioration de la recherche par auteur, et notes de modération internes sur les posts.

## Fonctionnalités en un coup d'œil

| Fonctionnalité | Emplacement |
|---|---|
| Recherche membre par email | ACP › Utilisateurs et groupes |
| Email de masse HTML + désinscription RFC 8058 | ACP › Général › Email |
| Logs et compteurs de désinscriptions | ACP › Extensions › Admin Helper |
| Correctif fiabilité cron_lock | transparent (arrière-plan) |
| Corps complet des posts sur recherche par auteur | `search.php?author_id=N&sr=posts` |
| **Notes de modération sur les posts** | viewtopic — modérateurs uniquement |

---

## Fonctionnalités détaillées

### 1. Recherche membre par email (ACP)

`ACP > Utilisateurs et groupes > Gérer les utilisateurs`

Ajoute un champ de recherche par adresse email dans le formulaire de gestion des membres. Utile quand le pseudo est inconnu mais que l'email est disponible (signalements d'abus, bounces, etc.).

---

### 2. Durcissement des emails de masse (ACP)

`ACP > Général > Communication client > Email`

- Contenu HTML optionnel avec génération automatique du fallback texte brut.
- Pied de page de désinscription optionnel.
- Mode envoi par destinataire (queue-safe) pour les gros volumes.
- En-têtes RFC 8058 conformes quand le mode one-click est activé :
  - `List-Unsubscribe`
  - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- Liens de désinscription signés HMAC avec expiration.
- Périmètres distincts : `massmail` et `forum_notify`.

---

### 3. Traitement des désinscriptions (RFC 8058)

- Intercepte les requêtes signées sur l'entrypoint du forum.
- Page de confirmation (`GET`) + action one-click (`POST`).
- Vérifie signature, expiration et mapping utilisateur avant application.
- Codes HTTP explicites : `200`, `400`, `403`, `410`.
- Suivi des désinscriptions manuelles depuis l'UCP.

---

### 4. Logs ACP désinscriptions

`ACP > Extensions > Admin Helper > Logs désinscriptions`

- Compteurs globaux (massmail / notifications forum).
- Journal détaillé : date, type, statut, membre, email, code HTTP, méthode, IP, expiration token, user-agent.
- Actions admin : restaurer les notifications email d'un membre, purger les anciennes notifications non lues.
- Suppression sélective et bulk des entrées.

---

### 5. Correctif cron_lock (depuis le 2026-03-06)

Le `cron.event_listener` phpBB par défaut ne libère pas le lock DB cron quand `run()` lève une exception — entraînant un lock orphelin de 3600 s qui bloque toutes les tâches cron.

**Fix 1 — safe_cron_runner_listener :**
`cron/safe_cron_runner_listener.php` remplace le service `cron.event_listener` via `config/services.yml` en ajoutant un `try-finally` qui garantit la libération du lock quoi qu'il arrive.

**Fix 2 — Watchdog :**
`tools/cron_watchdog.sh` tourne toutes les 5 minutes (crontab) et libère de force les locks orphelins de plus de 300 secondes. Log : `/var/log/phpbb_cron_watchdog.log`.

```bash
# Ajouter à la crontab (root) :
*/5 * * * * bash /chemin/vers/ext/bastien59960/adminhelper/tools/cron_watchdog.sh
```

> **À vérifier lors d'une mise à jour phpBB :** si la signature de `phpbb\cron\event\cron_runner_listener::on_kernel_terminate()` change, adapter `cron/safe_cron_runner_listener.php` en conséquence.

---

### 6. Corps complet des posts sur recherche par auteur (depuis le 2026-03-15)

`search.php?author_id=N&sr=posts`

phpBB tronque les longs messages et supprime les pièces jointes non-inline dans les résultats de recherche par auteur. Cette fonctionnalité corrige les deux problèmes :

- **Corps BBCode complet :** re-fetch de `post_text` depuis la DB et forçage de `display_text_only=false` pour que les images inline s'affichent correctement.
- **Pièces jointes non-inline :** sauvegardées avant que phpBB les détruise, puis rendues sous chaque post (miniature cliquable ou icône de fichier avec lien téléchargement et taille).

Aucune migration DB nécessaire — PHP/template uniquement.

---

### 7. Notes de modération sur les posts (depuis le 2026-03-15)

Les modérateurs et administrateurs peuvent attacher une note interne à n'importe quel post. Les notes sont **totalement invisibles des membres ordinaires**.

**Ajouter / modifier une note :**

Une icône SVG "bloc-notes + crayon" (avec un bord rouge danger) apparaît dans la barre d'actions des posts à côté de Éditer, Supprimer, Signaler, etc. — visible uniquement pour les utilisateurs ayant le droit `m_edit` sur le forum du post, ou les admins globaux. Un clic affiche un éditeur textarea inline. L'enregistrement crée ou remplace la note (une seule note par post).

Le bouton Éditer reçoit lui aussi un bord rouge pour le distinguer du bouton Citer.

**Lire une note :**

Si une note existe sur un post, une zone jaune apparaît en bas du contenu du post (sous la signature) et affiche :
- Le texte de la note.
- Qui l'a écrite et quand (« Note de *pseudo* le *date* »).
- Un bouton **Supprimer la note**.

Tous les modérateurs ayant `m_edit` sur le forum peuvent voir les notes des autres modérateurs.

**Page "Posts à modérer" :**

Un lien **"Posts à modérer"** apparaît dans le menu déroulant Accès rapide (sous "Brouillons"), visible uniquement pour les modérateurs et admins. Il ouvre un tableau listant tous les posts avec une note :

| Date note | Posée par | Post | Auteur post | Forum | Note | Action |
|---|---|---|---|---|---|---|

Accessible à `/app.php/adminhelper/mod-notes`.

**Sécurité :**
- Visibilité et accès en écriture contrôlés par la permission phpBB native `m_edit`.
- Protection CSRF via un token dédié — n'écrase jamais `{S_FORM_TOKEN}` de phpBB.
- Une note par post garantie par `UNIQUE KEY (post_id)` en base.

---

## Prérequis

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`
- MariaDB / MySQL (pour `INSERT ... ON DUPLICATE KEY UPDATE` dans les notes de modération)

---

## Installation

1. Copier `bastien59960/adminhelper` dans `ext/`.
2. Activer l'extension :

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
```

---

## Mise à jour

Après mise à jour des fichiers :

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
rm -rf /var/www/forum/cache/production/*
```

---

## Désinstallation

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

---

## Données stockées

| Table | Contenu |
|---|---|
| `adminhelper_unsubscribe_log` | Journal des désinscriptions (user, email, type, statut, IP, expiration token…) |
| `adminhelper_mod_notes` | Notes de modération (post_id UNIQUE, forum_id, texte, auteur, date) |

---

## Sécurité et vie privée

- Signatures de désinscription HMAC avec expiration.
- Notes de modération non rendues au niveau template pour les non-modérateurs.
- CSRF des notes dans une variable template isolée — ne touche pas à `{S_FORM_TOKEN}` de phpBB.
- Aucun token externe requis.

---

## Langues

- `fr`, `en`, `de`, `es`, `it`

---

## Licence

[GPL-2.0-only](LICENSE)

## Auteur

**Bastien** (`bastien59960`)
