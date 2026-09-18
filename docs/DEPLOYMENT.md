# Deployment

Targets a normal shared/VPS PHP hosting environment — no containers, no
orchestration, no Node build step.

## Requirements

- PHP 8.0+ (tested against 8.0.11; code avoids any 8.1+-only syntax, so it
  also runs unmodified on 8.1/8.2/8.3+).
- Extensions: `pdo_mysql`, `openssl`, `fileinfo`, `curl` (all standard,
  enabled by default in XAMPP and virtually every hosting PHP build).
- MySQL 8.x (or MariaDB 10.x — the schema uses only standard SQL/InnoDB
  features).
- Apache (or nginx) with the document root pointed at `public/` — **not**
  the project root. Everything outside `public/` (`app/`, `storage/`,
  `database/`, `.env`) must not be web-reachable.
- HTTPS in production — the session cookie is marked `Secure` whenever
  the request looks like HTTPS, so cookies silently stop working over
  plain HTTP in that configuration; don't run production without TLS.

## Steps

1. Upload the project outside (or with everything but `public/` blocked
   from) the web-reachable directory. If your host only lets you point a
   domain at one folder, point it at `public/` directly — `bootstrap.php`
   is `require`d via `dirname(__DIR__)`, so `public/index.php` reaches the
   app root either way.
2. Import `database/nithi_docket.sql` into a fresh MySQL database.
3. Copy `.env.example` to `.env`, fill in real `DB_*` credentials, a
   random `APP_KEY`, `APP_URL` (the real public URL), `APP_ENV=production`,
   `APP_DEBUG=false`.
4. `php database/seed_admin.php` once, then log in and change the
   password immediately from the Users page.
5. Ensure `storage/logs/` is writable by the web server's user
   (`chmod 750`, owned by that user — not world-writable).
6. Configure Firebase per `firebase/SETUP.md` if push notifications are
   needed; the app works without it, just with notifications honestly
   marked as undelivered.
7. Point Apache's `DocumentRoot` (or an nginx `root`) at `public/`.

## Apache example (vhost)

```apache
<VirtualHost *:443>
    ServerName docket.example.lk
    DocumentRoot /var/www/nithi-docket/public
    <Directory /var/www/nithi-docket/public>
        AllowOverride None
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/docket.example.lk/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/docket.example.lk/privkey.pem
</VirtualHost>
```

No `.htaccess`/rewrite rules are required — every request goes through
`public/index.php`'s own router already (point the vhost at `public/` and
it just works; there's no pretty-URL rewriting to configure since the
front controller pattern here doesn't need one — `public/index.php` is
hit directly by the app's own relative asset/links via `Response::url()`,
which is built from `APP_URL`).

## Backup

- **Database**: standard `mysqldump`:
  ```bash
  mysqldump -u root nithi_docket > backup-$(date +%F).sql
  ```
  No automatic backup scheduling is implemented — set up a cron job or
  your host's managed MySQL backups; this project doesn't claim automatic
  backups it doesn't perform.
- **Restore**: `mysql -u root nithi_docket < backup-2026-09-17.sql`.

## Not built (documented, not an oversight)

- No CI pipeline, no zero-downtime deploy tooling, no queue/worker
  process — genuinely unnecessary for this application's scale (Section
  34/39 of the original brief explicitly says not to over-engineer this).
- No document upload/attachment feature — deliberately out of scope for
  this deployment.
