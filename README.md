# Bastien59960 Admin Helper - phpBB 3.3+ Extension

[Français](README.fr.md)

**Harden ACP email workflows and unsubscribe compliance.**

When admin-to-member communication quality matters, unsubscribe handling and auditability must be reliable. Bastien59960 Admin Helper extends phpBB ACP with practical tools for safer mass email operations, RFC 8058 one-click unsubscribe support, and operational unsubscribe logs.

## Why install it

- Search members by email directly from ACP user management.
- Send cleaner mass emails with optional HTML + plain-text multipart.
- Add compliant unsubscribe headers and links.
- Track unsubscribe events with clear ACP logs and counters.

## Key features

### ACP user management helper

- Adds email-based member lookup in:
  - `ACP > Users and Groups > Manage users`
- Improves support/admin workflows when username is unknown.

### Mass email hardening (ACP)

In `ACP > General > Client communication > Email`:

- Optional HTML email content with sanitized markup.
- Automatic plain-text fallback generation when HTML is provided.
- Optional unsubscribe footer insertion.
- One-click mode can force recipient-specific queue-safe sending behavior.

### One-click unsubscribe (RFC 8058)

- Adds compliant headers when enabled:
  - `List-Unsubscribe`
  - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- Generates signed, expiring unsubscribe links.
- Supports distinct unsubscribe scopes:
  - `massmail`
  - `forum_notify`

### Unsubscribe request handling

- Intercepts signed unsubscribe requests on forum entrypoint.
- Provides confirmation page (`GET`) and one-click action (`POST`).
- Verifies signature, expiry, and user mapping before applying changes.
- Uses explicit HTTP status handling (`200`, `400`, `403`, `410`).

### ACP unsubscribe logs and counters

In `ACP > Extensions > Admin Helper > Unsubscribe logs`:

- Global counters for massmail and forum notification subscription states.
- Detailed event log:
  - date, type, status, member, email, HTTP code, method, IP, token expiry, user-agent
- Tracks manual unsubscribe actions from UCP preference changes.
- Admin action to restore forum email notifications for a member.
- Cleanup action for old unread notifications.

## Requirements

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`

## Installation

1. Copy `bastien59960/adminhelper` into `ext/`.
2. Enable the extension:

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
```

## Update

After updating files:

```bash
php bin/phpbbcli.php db:migrate
php bin/phpbbcli.php cache:purge
```

## Uninstall

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

## Quick ACP setup

Recommended in **ACP > General > Client communication > Email**:

- Enable unsubscribe footer.
- Enable one-click unsubscribe headers.
- Use queue sending for large recipient sets.
- Use HTML mode only when needed and keep message content simple.

Recommended in **ACP > Extensions > Admin Helper > Unsubscribe logs**:

- Verify counters and recent event statuses.
- Use restore action only for confirmed user requests.
- Periodically clean old unread notifications if required by your policy.

## Stored data (summary)

Main AdminHelper table:

- `adminhelper_unsubscribe_log`
  - `user_id`, `user_email`, `unsubscribe_type`, `token_expires_at`
  - `http_status`, `event_status`, `request_method`, `request_ip`, `request_user_agent`, `logged_at`

## Security and privacy

- Unsubscribe signatures are HMAC-based and time-bounded.
- Invalid/abusive unsubscribe attempts are rate-limited in logs.
- Email sending must not be blocked by header/formatting errors (fail-open behavior).
- No external API tokens are required for core functionality.

## Known limits

- One-click behavior depends on recipient provider support for RFC 8058 headers.
- High-volume sends may be slower when one-click recipient-specific mode is enabled.
- The extension updates phpBB preferences; it does not manage third-party suppression lists.

## Language coverage

Language files are provided for:

- `fr`, `en`, `de`, `es`, `it`

## License

[GPL-2.0-only](LICENSE)

## Author

**Bastien** (`bastien59960`)
