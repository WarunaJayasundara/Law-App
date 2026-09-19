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
- Per-IP throttle: more than 20 failed sign-ins from one address in 10
  minutes (across any usernames) are refused outright, which stops
  username-spraying that the per-account lockout cannot see.
- Unknown usernames still run a full bcrypt verification against a dummy hash
  (about 64 ms, versus 67 ms for a real account), so response time does not
  reveal which usernames exist. The error message is identical either way.
- Session ID regenerated on login (`Session::regenerate()`) to prevent
  session fixation.
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` when served over
  HTTPS, with a configurable idle timeout enforced server-side (not just
  cookie expiry).
- Password rules (`PasswordPolicy`): 10 to 72 bytes (bcrypt ignores anything
  longer, so it is refused rather than silently truncated), not a published
  default, not equal to the username or email.
- Every user can change their own password (My account). A wrong "current
  password" there counts toward the same lockout, so a hijacked session
  cannot be used to guess it.
- In production (`APP_ENV=production`) signing in with the published default
  password forces a password change before any other page is reachable, and
  `seed_admin.php` refuses to create an admin with that password.

## Web-server exposure

- The document root is `public/`; `.env`, the Firebase key, source, SQL and
  logs live outside it.
- For hosts that serve the whole project folder, the root `.htaccess` returns
  403 for those paths and for dotfiles. Verified on real Apache 2.4:
  `.env`, `firebase/service-account.json`, `app/`, `composer.json`, SQL,
  logs, `.git`, `vendor` and the traversal variants tried (`/public/../.env`,
  `/%2e%65nv`, `/public/%2e%2e/.env`, `/assets/../../.env`) all fail.

## HTTP response headers

Sent on every dynamic response (`SecurityHeaders`): a Content-Security-Policy
restricting scripts, styles, fonts and connections to this site plus the
specific CDNs and Google endpoints in use; `frame-ancestors 'none'` and
`X-Frame-Options: DENY` (no clickjacking); `object-src 'none'`,
`base-uri 'self'`, `form-action 'self'`; `X-Content-Type-Options: nosniff`;
`Referrer-Policy`; `Permissions-Policy`; `Cache-Control: no-store` so personal
records are not cached or shown by the Back button after logout; and HSTS
when the request is HTTPS.

## Abuse limits

- "Resend SMS" is only available for Received deeds and is limited to one
  send per 60 seconds per deed (computed in SQL, so timezones can't skew it),
  so a double-click or a curious user can't burn SMS credit.
- Deed numbers are restricted to letters, digits, space and `/ - .`. The number
  is inserted into an SMS, so free text or links can't be smuggled to buyers.
- The editable SMS message is validated: required `{deed_number}` placeholder,
  no other placeholders, at most 700 characters, no control characters.
- Duplicate deed numbers are rejected on edit as well as on create.

## Proxies and client IP

`X-Forwarded-For` / `X-Forwarded-Proto` are only believed when
`TRUST_PROXY=true`. The rightmost `X-Forwarded-For` entry is used (the address
the trusted proxy itself saw), so a client cannot forge its own IP for the
audit log or the throttle. Verified: a request claiming `6.6.6.6` through a
proxy that appended `198.51.100.7` is recorded as `198.51.100.7`.

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

## Known limitations (stated, not hidden)

- The CSP still allows `'unsafe-inline'` scripts and styles because a few
  templates use inline handlers and `<style>` blocks. Removing that would need
  those moved to files plus nonces.
- No two-factor authentication.
- The audit log stores names, NICs and phone numbers as they were entered,
  and is visible to anyone with `audit.view`.
- Database backups and HTTPS certificates are the host's responsibility.
- The buyer/seller record is shared by NIC: entering a deed with an existing
  NIC updates that person's name for all of their deeds. An empty phone number
  no longer erases an existing one.

## Error handling

`bootstrap.php` installs a global exception handler that logs the real
error server-side (`storage/logs/app.log`) and shows the user a generic
message in production (`APP_DEBUG=false`) — stack traces, file paths, and
SQL errors are never shown to end users outside local development.
