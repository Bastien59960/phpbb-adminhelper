# Bastien59960 Admin Helper - Extension phpBB 3.3+

[English](README.md)

**Renforcez les workflows email ACP et la conformite desinscription.**

Quand la qualite des communications admin vers membres est critique, la desinscription et l'audit doivent etre fiables. Bastien59960 Admin Helper etend l'ACP phpBB avec des outils pratiques pour des envois de masse plus solides, la conformite RFC 8058 one-click et des logs exploitables.

## Pourquoi l'installer

- Rechercher un membre par email directement dans l'ACP.
- Envoyer des emails de masse plus propres (HTML optionnel + texte fallback).
- Ajouter des en-tetes et liens de desinscription conformes.
- Suivre les evenements de desinscription avec compteurs et logs ACP.

## Fonctionnalites principales

### Aide gestion membres ACP

- Ajoute la recherche membre par email dans:
  - `ACP > Utilisateurs et groupes > Gerer les utilisateurs`
- Facilite le support admin quand le pseudo est inconnu.

### Durcissement des emails de masse (ACP)

Dans `ACP > General > Communication client > Email`:

- Contenu email HTML optionnel avec sanitation basique.
- Generation automatique d'un fallback texte si HTML fourni.
- Footer de desinscription optionnel.
- Mode one-click pouvant forcer un envoi queue-safe par destinataire.

### Desinscription one-click (RFC 8058)

- Ajoute les en-tetes conformes quand active:
  - `List-Unsubscribe`
  - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- Genere des liens signes avec expiration.
- Gere deux portees de desinscription:
  - `massmail`
  - `forum_notify`

### Traitement des requetes de desinscription

- Intercepte les requetes signees sur l'entrypoint forum.
- Sert une page de confirmation (`GET`) et traite l'action one-click (`POST`).
- Verifie signature, expiration et mapping membre avant application.
- Utilise des statuts HTTP explicites (`200`, `400`, `403`, `410`).

### Logs ACP et compteurs de desinscription

Dans `ACP > Extensions > Admin Helper > Logs desinscription`:

- Compteurs globaux des etats abonnement massmail et forum_notify.
- Journal detaille:
  - date, type, statut, membre, email, HTTP, methode, IP, expiration token, user-agent
- Trace aussi les desinscriptions manuelles faites depuis l'UCP.
- Action admin pour reactiver les notifications email forum d'un membre.
- Action de nettoyage des anciennes notifications non lues.

## Prerequis

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`

## Installation

1. Copier `bastien59960/adminhelper` dans `ext/`.
2. Activer l'extension:

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
```

## Mise a jour

Apres mise a jour des fichiers:

```bash
php bin/phpbbcli.php db:migrate
php bin/phpbbcli.php cache:purge
```

## Desinstallation

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

## Configuration ACP rapide

Recommande dans **ACP > General > Communication client > Email**:

- Activer le footer de desinscription.
- Activer les en-tetes one-click.
- Utiliser la queue pour les gros volumes.
- Activer le HTML seulement si necessaire.

Recommande dans **ACP > Extensions > Admin Helper > Logs desinscription**:

- Verifier les compteurs et statuts recents.
- Utiliser l'action de restauration seulement sur demande membre validee.
- Nettoyer periodiquement les anciennes notifications non lues selon votre politique.

## Donnees stockees (resume)

Table principale AdminHelper:

- `adminhelper_unsubscribe_log`
  - `user_id`, `user_email`, `unsubscribe_type`, `token_expires_at`
  - `http_status`, `event_status`, `request_method`, `request_ip`, `request_user_agent`, `logged_at`

## Securite et vie privee

- Signatures de desinscription HMAC avec expiration.
- Rate-limit des logs pour limiter les floods d'evenements invalides.
- Envoi email preserve meme en cas d'erreur de decoration d'en-tetes (fail-open).
- Aucun token externe requis pour les fonctions principales.

## Correctif systeme : cron_lock phpBB (safe_cron_runner_listener)

**Probleme identifie (2026-03-06) :** Le listener core phpBB `cron.event_listener`
(`phpbb\cron\event\cron_runner_listener`) ne libere pas le `cron_lock` (DB) lorsque
`$task->run()` leve une exception ou lorsque le `cron_type` demande est introuvable.
Le lock reste orphelin pendant 3600s (1h), bloquant tous les crons du forum.

**Correctif applique via extension (sans toucher le core phpBB) :**

Le fichier `cron/safe_cron_runner_listener.php` remplace le service `cron.event_listener`
via la declaration dans `config/services.yml`. Il ajoute un `try-finally` qui garantit
que `$cron_lock->release()` est toujours appele, meme en cas d'exception.

```yaml
# config/services.yml
cron.event_listener:
    class: bastien59960\adminhelper\cron\safe_cron_runner_listener
    arguments:
        - '@cron.lock_db'
        - '@cron.manager'
        - '@request'
    tags:
        - { name: kernel.event_subscriber }
```

**A verifier lors d'une mise a jour phpBB :** Si la signature de
`phpbb\cron\event\cron_runner_listener::on_kernel_terminate()` change, adapter
`cron/safe_cron_runner_listener.php` en consequence.

## Watchdog systeme cron_lock

Le script `tools/cron_watchdog.sh` surveille le `cron_lock` et le reinitialise
si le lock est orphelin depuis plus de 5 minutes (au lieu des 3600s phpBB).

**Installation crontab (une fois, en root) :**

```bash
*/5 * * * * bash /var/www/forum/ext/bastien59960/adminhelper/tools/cron_watchdog.sh
```

**Logs :** `/var/log/phpbb_cron_watchdog.log`

## Limites connues

- Le comportement one-click depend du support RFC 8058 du fournisseur destinataire.
- Les gros volumes peuvent etre plus lents en mode one-click par destinataire.
- L'extension met a jour les preferences phpBB, sans gerer les suppressions externes.

## Langues

Fichiers de langue disponibles:

- `fr`, `en`, `de`, `es`, `it`

## Licence

[GPL-2.0-only](LICENSE)

## Auteur

**Bastien** (`bastien59960`)
