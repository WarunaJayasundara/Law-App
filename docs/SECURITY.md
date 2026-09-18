# Security controls

Concrete, implemented controls — not a checklist of intentions.

## SQL injection

Every query goes through PDO prepared statements with bound parameters
(`app/Models/*.php`). No user input is ever concatenated into SQL.
`PDO::ATTR_EMULATE_PREPARES` is explicitly disabled (`app/Core/Database.php`)
so MySQL's own native prepared-statement protocol is used, not PHP-side
string substitution.

## XSS

All dynamic output in views goes through `View::e()` (an
`htmlspecialchars` wrapper) before being echoed. Nothing user-supplied is
ever printed unescaped.

## CSRF

Every `POST`/`DELETE` route requires a `_csrf` token matching the one
stored in the session (`app/Core/Csrf.php`, checked centrally in
`Router::dispatch()` — a controller can't forget to check it). A failed
check returns HTTP 419, not a silent pass-through.

## Authentication

- Passwords hashed with `password_hash()` (bcrypt/argon2 depending on PHP
  build), verified with `password_verify()` — never compared as plaintext.
- Failed-login lockout: 5 consecutive failures locks the account for 15
  minutes (`users.failed_login_attempts` / `locked_until`).
- Session ID regenerated on login (`Session::regenerate()`) to prevent
  session fixation.
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` when served over
  HTTPS, with a configurable idle timeout enforced server-side (not just
  cookie expiry).

## Authorization

Every sensitive controller action calls `$this->requirePermission(...)`
before doing anything — this is a server-side check against the user's
role's permissions (`role_permissions` table, re-read fresh each request,
not cached in the session), independent of what the UI shows or hides.
Verified live: a `staff` account with no `audit.view` permission gets a
real 403 when hitting `/audit-logs` directly by URL, not just a hidden nav
link.

## Secrets

`.env` is gitignored; `.env.example` ships only placeholders.
`firebase/service-account.json` is gitignored. No secret is ever sent to
the browser — the Firebase server credentials never leave
`FirebaseNotificationService`.

## Audit trail

Every state-changing action (login/logout, deed create/update/archive,
registration updates, mark reviewed/received, notification send,
user create/update) is recorded in `audit_logs` with the actor, action,
entity, before/after values (for updates), IP, and user agent — visible
only to users with `audit.view` permission, and there is no UI or endpoint
that lets any role modify or delete an audit row.

## Error handling

`bootstrap.php` installs a global exception handler that logs the real
error server-side (`storage/logs/app.log`) and shows the user a generic
message in production (`APP_DEBUG=false`) — stack traces, file paths, and
SQL errors are never shown to end users outside local development.
