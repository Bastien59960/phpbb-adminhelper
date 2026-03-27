# Bastien59960 Admin Helper — phpBB 3.3+ Extension

[Français](README.fr.md)

Admin Helper groups together several practical phpBB administration and moderation tools in a single extension:

- safer ACP mass email with RFC 8058 unsubscribe support
- unsubscribe audit logs and restore tools
- `cron_lock` reliability hardening
- full post body and non-inline attachments on author search
- internal moderation notes on posts
- AI-image declaration and automatic source detection on attachments

## Features at a glance

| Feature | Where |
|---|---|
| Search members by email | ACP › Users and Groups |
| Mass email hardening + one-click unsubscribe | ACP › General › Email |
| Unsubscribe logs and counters | ACP › Extensions › Admin Helper |
| `cron_lock` watchdog and safe release | background |
| Full post body on author search | `search.php?author_id=N&sr=posts` |
| Moderation notes on posts | viewtopic — moderators only |
| AI-generated image declaration and scan | posting + ACP |

## AI attachment feature

`ACP > Extensions > Admin Helper > AI-generated images`

Admin Helper can mark image attachments as AI-generated and show a public warning under the image on the forum.

- A manual checkbox lets posters declare that an image is AI-generated.
- Strong technical signatures are auto-detected at upload time and during batch scans.
- When detection is reliable, the checkbox is auto-checked and locked.
- The detected AI source is shown publicly only when it is identified automatically from the file itself.
- Current strong signals include C2PA / Content Credentials, known AI-tool metadata, and preserved prompt/parameter payloads.
- Existing images can be scanned in bulk from ACP or CLI.

### Current detection scope

- `Gemini` can be identified when Google/Gemini C2PA markers are present.
- `ChatGPT` can be identified when OpenAI / ChatGPT C2PA or metadata markers are present.
- No AI source is assigned manually by the extension. If the file does not prove the source, the forum only shows the generic AI warning.

### CLI

```bash
php bin/phpbbcli.php adminhelper:attachment-ai-scan --batch=1000 --max-seconds=0
```

## Stored data

| Table | Purpose |
|---|---|
| `adminhelper_unsubscribe_log` | Unsubscribe audit log |
| `adminhelper_mod_notes` | Internal moderation notes on posts |
| `adminhelper_attachment_ai` | AI-image state for attachments, scan status, detected source |

## Requirements

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`
- MariaDB / MySQL

## Installation

1. Copy `bastien59960/adminhelper` into `ext/`.
2. Enable the extension:

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
php bin/phpbbcli.php cache:purge
```

## Update

After updating files:

```bash
php bin/phpbbcli.php db:migrate -n
php bin/phpbbcli.php cache:purge
```

## Uninstall

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

## Language coverage

- Full current coverage: `fr`, `en`
- Legacy files also exist for `de`, `es`, `it`, but the newest AI-attachment strings are not yet fully translated there.

## Author

**Bastien** (`bastien59960`)
