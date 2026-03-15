# Bastien59960 Admin Helper — phpBB 3.3+ Extension

[Français](README.fr.md)

A collection of practical admin and moderation utilities for phpBB forums: safer mass email, RFC 8058 unsubscribe compliance, audit logs, cron reliability, improved author search, and internal moderation notes on posts.

## Features at a glance

| Feature | Where |
|---|---|
| Search members by email | ACP › Users and Groups |
| Mass email with HTML + RFC 8058 unsubscribe | ACP › General › Email |
| Unsubscribe logs and counters | ACP › Extensions › Admin Helper |
| cron_lock reliability fix | transparent (background) |
| Full post body on author search | `search.php?author_id=N&sr=posts` |
| **Moderation notes on posts** | viewtopic — moderators only |

---

## Detailed features

### 1. Member search by email (ACP)

`ACP > Users and Groups > Manage users`

Adds an email field to the user search form. Useful when a username is unknown but an email address is available (e.g. abuse reports or bounced email follow-ups).

---

### 2. Mass email hardening (ACP)

`ACP > General > Client communication > Email`

- Optional HTML content with automatic plain-text fallback.
- Optional unsubscribe footer.
- Per-recipient queue-safe sending mode for large volumes.
- RFC 8058 compliant headers when one-click mode is enabled:
  - `List-Unsubscribe`
  - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- Signed, expiring unsubscribe links (HMAC).
- Separate scopes: `massmail` and `forum_notify`.

---

### 3. Unsubscribe request handling (RFC 8058)

- Intercepts signed unsubscribe requests on forum entry point.
- Confirmation page (`GET`) and one-click action (`POST`).
- Verifies signature, expiry, and user mapping before applying.
- Explicit HTTP status codes: `200`, `400`, `403`, `410`.
- Tracks manual UCP preference changes as unsubscribe events.

---

### 4. ACP unsubscribe logs

`ACP > Extensions > Admin Helper > Unsubscribe logs`

- Global counters (massmail / forum notifications).
- Detailed event log: date, type, status, member, email, HTTP code, method, IP, token expiry, user-agent.
- Admin actions: restore forum notification emails for a member, purge stale unread notifications.
- Selective and bulk log entry deletion.

---

### 5. cron_lock fix (since 2026-03-06)

phpBB's default `cron.event_listener` does not release the cron DB lock when `run()` throws an exception — leading to a 3600 s orphan lock that blocks all cron tasks.

**Fix 1 — safe_cron_runner_listener:**
`cron/safe_cron_runner_listener.php` replaces the `cron.event_listener` service via `config/services.yml`, wrapping `run()` in a `try-finally` that always releases the lock.

**Fix 2 — Watchdog:**
`tools/cron_watchdog.sh` runs every 5 minutes (crontab) and forcibly releases orphan locks older than 300 seconds. Log: `/var/log/phpbb_cron_watchdog.log`.

```bash
# Add to crontab (root):
*/5 * * * * bash /path/to/ext/bastien59960/adminhelper/tools/cron_watchdog.sh
```

> **Note for phpBB updates:** if the signature of `phpbb\cron\event\cron_runner_listener::on_kernel_terminate()` changes, update `cron/safe_cron_runner_listener.php` accordingly.

---

### 6. Full post body on author search (since 2026-03-15)

`search.php?author_id=N&sr=posts`

phpBB truncates long posts and drops non-inline attachments in author search results. This feature restores both:

- **Full BBCode body:** re-fetches `post_text` from DB and forces `display_text_only=false` so inline images render correctly.
- **Non-inline attachments:** stored before phpBB destroys them, then rendered per post as thumbnail (clickable) or file icon with download link and file size.

No database migration required — pure PHP/template.

---

### 7. Moderation notes on posts (since 2026-03-15)

Moderators and administrators can attach an internal note to any post. Notes are **completely invisible to regular members**.

**Adding / editing a note:**

A notepad+pencil SVG icon (with a red danger border) appears in the post action bar next to Edit, Delete, Report, etc. — visible only to users with `m_edit` permission on that forum, or to global admins. Clicking the icon reveals an inline textarea editor. Saving creates or replaces the note (one note per post).

The Edit post button also gains a red border to reduce confusion with the Quote button.

**Reading a note:**

If a note exists on a post, a yellow box appears at the bottom of the post content (below the signature), showing:
- The note text.
- Who wrote it and when ("Note by *username* on *date*").
- A **Delete note** button.

All moderators with `m_edit` on that forum can see each other's notes.

**Posts to moderate page:**

A **"Posts to moderate"** link appears in the Quick Links dropdown menu (below Drafts), visible only to moderators and admins. It opens a table listing all posts with notes:

| Date | Written by | Post | Post author | Forum | Note | Action |
|---|---|---|---|---|---|---|

Available at `/app.php/adminhelper/mod-notes`.

**Security:**
- Visibility and write access controlled by phpBB's native `m_edit` permission.
- CSRF protection via a dedicated form token — never overwrites phpBB's own `{S_FORM_TOKEN}`.
- One note per post enforced by `UNIQUE KEY (post_id)` at DB level.

---

## Requirements

- PHP `>= 7.1.3`
- phpBB `>= 3.3.0`
- MariaDB / MySQL (for `INSERT ... ON DUPLICATE KEY UPDATE` in mod notes)

---

## Installation

1. Copy `bastien59960/adminhelper` into `ext/`.
2. Enable the extension:

```bash
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
```

---

## Update

After updating files:

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:enable bastien59960/adminhelper
rm -rf /var/www/forum/cache/production/*
```

---

## Uninstall

```bash
php bin/phpbbcli.php extension:disable bastien59960/adminhelper
php bin/phpbbcli.php extension:purge bastien59960/adminhelper
```

---

## Stored data

| Table | Purpose |
|---|---|
| `adminhelper_unsubscribe_log` | Unsubscribe event log (user, email, type, status, IP, token expiry…) |
| `adminhelper_mod_notes` | Moderation notes (post_id UNIQUE, forum_id, note_text, author, date) |

---

## Security and privacy

- Unsubscribe signatures are HMAC-based and time-bounded.
- Moderation notes are never exposed to non-moderator users at template level.
- CSRF for mod notes uses an isolated token variable — does not overwrite phpBB's `{S_FORM_TOKEN}`.
- No external API tokens required.

---

## Language coverage

- `fr`, `en`, `de`, `es`, `it`

---

## License

[GPL-2.0-only](LICENSE)

## Author

**Bastien** (`bastien59960`)
