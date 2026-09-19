# Nithi Docket — Deed Registry Tracker

A secure, production-shaped PHP/MySQL rebuild of the original localStorage
HTML prototype for tracking property deed registrations between buyers and
sellers through to Land Registry completion.

Rebuilt from the prototype, not converted from it — see
[docs/PROTOTYPE_ANALYSIS.md](docs/PROTOTYPE_ANALYSIS.md) for what changed
and why.

## Stack

- **Backend**: PHP 8.0+ (compatible with 8.2+ too), plain MVC, PDO with
  prepared statements. No framework, no Composer dependency at runtime —
  autoloading is a ~15-line `spl_autoload_register` in `bootstrap.php`.
  Composer is used *only* as a dev dependency to install PHPUnit for tests.
- **Frontend**: server-rendered PHP views + Bootstrap 5 (CDN) + a small
  amount of vanilla JS for the Firebase push-notification opt-in. No
  build step, no SPA framework.
- **Database**: MySQL 8.x, normalized schema with foreign keys, indexes,
  and a full audit trail (`database/nithi_docket.sql`).
- **Notifications**: real Firebase Cloud Messaging (HTTP v1 API, signed
  directly with PHP's `openssl` extension — no Firebase Admin SDK
  dependency) for internal staff/admin alerts. Real SMS via text.lk for
  buyer/seller mobile numbers (FCM can't reach them — see
  `firebase/SETUP.md`'s note, and `docs/SMS_SETUP.md`).

## Quick start (XAMPP)

1. **Database**
   ```bash
   mysql -u root < database/nithi_docket.sql
   ```
2. **Config**
   ```bash
   cp .env.example .env
   ```
   Edit `.env`: set `APP_URL` to wherever this will be served from (e.g.
   `http://localhost:8090` if using PHP's built-in server — see below),
   set `APP_ENV=local` and `APP_DEBUG=true` for local development
   (the example file defaults to production-safe values), and set
   `SEED_ADMIN_PASSWORD` to something real before the next step.
3. **Seed the first admin account**
   ```bash
   php database/seed_admin.php
   ```
   Log in with `SEED_ADMIN_USERNAME` / `SEED_ADMIN_PASSWORD` from `.env`,
   then change the password from **My account** (bottom of the sidebar).
   Already have a database from before the Settings page existed? Run
   `database/migrations/2026_09_19_settings.sql` once.
4. **Serve it**
   - Under XAMPP: place/symlink this folder under `htdocs`, point Apache's
     document root (or an alias) at `public/`, and set `APP_URL`
     accordingly.
   - For quick local testing without configuring Apache:
     ```bash
     php -S localhost:8090 -t public
     ```
## Notifications

**SMS to the buyer and seller** is sent automatically, once, when a deed is
marked **Received**, to the mobile numbers on the deed. The message is the
office's two-language confirmation and an **admin can change the wording**
under Settings (only `{deed_number}` varies per deed). It uses
[text.lk](https://text.lk) — see [docs/SMS_SETUP.md](docs/SMS_SETUP.md),
including how billing works (a Sinhala message costs several SMS units).

**Firebase Cloud Messaging** sends an internal push to staff when a deed is
received, and powers the Notifications page — see
[firebase/SETUP.md](firebase/SETUP.md).

These are two separate systems for two different audiences: Firebase can't
reach a phone number, and text.lk doesn't know about app accounts.

Every notification/SMS attempt is recorded honestly with a real error
message if it fails — nothing pretends to have sent something that wasn't
actually sent.

## Testing

```bash
composer require --dev phpunit/phpunit:^9.6   # once, PHP 8.0-compatible
php vendor/bin/phpunit
```

Unit tests cover pure logic that doesn't need a database (the registration
→ status derivation rules, input validation). See
[docs/TESTING.md](docs/TESTING.md) for what's covered, what needs a live
DB and was instead verified by hand against a real MySQL instance during
development, and how to extend the suite.

## Security

See [docs/SECURITY.md](docs/SECURITY.md) for the concrete controls
implemented (prepared statements everywhere, CSRF tokens on every
state-changing request, password hashing, session hardening, server-side
authorization on every sensitive action).

## Deployment

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — standard Apache + PHP 8.x +
MySQL 8.x hosting, HTTPS required in production, no containers/orchestration
needed.

## What's implemented vs. what needs configuration

| Feature | Status |
|---|---|
| Auth, RBAC, sessions, CSRF | Implemented |
| Deed CRUD, search, filter, pagination | Implemented |
| Submitted → Reviewed → Received workflow (explicit action buttons) | Implemented |
| Audit log | Implemented |
| Firebase push notifications (internal staff/admin) | Implemented and connected to a real project (see `firebase/SETUP.md`) |
| SMS to buyer/seller (text.lk) | Implemented and verified with real sends, using a real approved Sender ID (`YUSORA`). Sent once, on Received. Needs SMS credit — see `docs/SMS_SETUP.md` |
| Admin-editable SMS wording (Settings page) | Implemented, with live preview and per-recipient SMS-part estimate |
| Change your own password (My account) | Implemented; forced on first login in production if the published default is used |
| Login throttling, security headers/CSP, hardened `.htaccess` | Implemented — see `docs/SECURITY.md` |
| Self-registration | Intentionally not built — accounts are created by an admin under Users, since this handles legal/personal records |

## Status model

Deliberately simple: **Submitted → Reviewed → Received**, moved forward by
explicit "Mark reviewed" / "Mark received" staff actions on the deed page
(not inferred from whether some field happens to be filled in). Register
date, day book no., and new folio no. are plain reference fields you can
fill in whenever — they don't drive the status. The confirmation SMS goes
out automatically when a deed becomes Received; it is a side effect of that
step, not a 4th status. "Resend SMS" is a safety net for a failed send.

Document upload was deliberately not built — the registration workflow
here doesn't require attaching files.

## Known limitation from this build session

Live-tested against a real MySQL instance during development (not just
`php -l` syntax checks): this caught and fixed 4 real bugs where the same
named PDO placeholder was reused twice in one query (`:login`, `:q`,
`:report_received`, `:created_by`) — MySQL's native prepared statements
(as opposed to PDO's emulated ones) reject that, and it only surfaces at
runtime as `SQLSTATE[HY093]: Invalid parameter number`, not at parse time.
Full end-to-end walkthrough (login → create deed → Reviewed/Received
workflow → notification attempt → audit log → RBAC enforcement for a
second, lower-privileged `staff` account) was verified live in-browser;
see `docs/TESTING.md`.
