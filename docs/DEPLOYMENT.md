# Deployment

Targets a normal PHP hosting environment: a small VPS, cPanel/shared hosting,
or an office PC. No containers, no build step.

## Requirements

- PHP 8.0+ (developed on 8.0.11; avoids 8.1+-only syntax, so it also runs on
  8.1 to 8.4).
- Extensions: `pdo_mysql`, `openssl`, `curl`, `mbstring` (standard on
  virtually every host).
- MySQL 8.x or MariaDB 10.x.
- **Outbound HTTPS from the server.** The app calls text.lk (SMS) and Google
  (Firebase push). Hosts that block outgoing connections will make every
  SMS fail, so test this before choosing a host (see "Free hosting" below).
- HTTPS for the public site. Push notifications and service workers require
  it, and the session cookie is only marked `Secure` over HTTPS.

## Steps

1. **Upload the project.** Either
   - point the domain's document root at `public/` (best), or
   - if the host only serves the project folder itself (typical of shared
     hosting), upload the whole folder as-is: the root `.htaccess` blocks
     everything private and serves only `public/`. This needs Apache with
     `mod_rewrite` and `AllowOverride All`.
2. **Database.** Create an empty database and import
   `database/nithi_docket.sql`. An existing database created before the
   editable SMS message feature needs
   `database/migrations/2026_09_19_settings.sql` once (safe to re-run).
3. **Config.** Copy `.env.example` to `.env` and set:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_URL` to the real public address (no trailing slash)
   - `DB_*` credentials
   - `SEED_ADMIN_PASSWORD` to a real password (10+ characters). In production
     the seed script refuses the published example password.
   - `TEXTLK_API_TOKEN` and `TEXTLK_SENDER_ID`
   - `TRUST_PROXY=true` **only** if a reverse proxy or the host's load
     balancer sits in front of PHP (see below).

   Real environment variables override `.env`, for hosts that inject
   configuration instead of a file.
4. **First admin.** `php database/seed_admin.php`, then sign in. If the
   password is a published default you are forced to change it before
   anything else works.
5. **Writable logs.** `storage/logs/` must be writable by the web server user
   and not world-writable.
6. **Firebase (optional).** Follow `firebase/SETUP.md`. Three files are
   deliberately not in git and must be uploaded by hand:
   `firebase/service-account.json` (a secret), `public/assets/js/firebase-config.js`
   and `public/firebase-messaging-sw.js`.
7. **Check it.** Open `/.env` and `/firebase/service-account.json` on your
   domain. Both must return 403 or 404, never file contents.

## Behind a proxy

If a reverse proxy or platform load balancer terminates HTTPS in front of
PHP, set `TRUST_PROXY=true`. The app then trusts `X-Forwarded-Proto` (for
HTTPS/HSTS/`Secure` cookies) and uses the rightmost `X-Forwarded-For` entry as
the client address (for the audit log and the login throttle). Do **not**
enable it on a server that clients reach directly, or they could forge those
headers.

## Apache

Option A, document root = `public/` (`public/.htaccess` handles routing):

```apache
<VirtualHost *:443>
    ServerName docket.example.lk
    DocumentRoot /var/www/nithi-docket/public
    <Directory /var/www/nithi-docket/public>
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/docket.example.lk/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/docket.example.lk/privkey.pem
</VirtualHost>
```

Without `AllowOverride All` (or an equivalent rewrite to `index.php`) every
URL except `/` returns 404 on Apache: only PHP's built-in server routes them
automatically.

Option B, document root = project folder: use the root `.htaccess` as shipped.
This was tested on a real Apache 2.4 with PHP 8.0: `.env`, the Firebase key,
source, SQL, logs and dependencies all return 403, path-traversal variants
fail, and the login flow works.

## nginx

Point `root` at `public/`, send unknown URLs to `index.php`
(`try_files $uri /index.php?$query_string;`), and deny dotfiles and anything
outside `public/`. The `.htaccess` files are Apache-only.

## Free hosting: what fits and what doesn't

This app needs PHP, MySQL, **outbound HTTPS**, and a private place for a
Firebase key. Many free shared hosts fail on the third point (InfinityFree,
for one, is widely reported to block outgoing cURL/API requests), which would
make SMS fail silently. Check with a one-line cURL test before committing.

Realistic options:

1. **Run it in the office.** XAMPP on an always-on office PC, opened to the
   local network. Zero cost, data never leaves the premises, no outbound
   restrictions. Needs the PC to stay on and regular backups.
2. **Oracle Cloud Always Free VM.** A real Linux server (per Oracle's docs:
   up to two 1 GB AMD micro VMs, or an Ampere A1 allowance of 2 OCPU / 12 GB),
   with no outbound restrictions. You set up Apache, PHP, MariaDB and HTTPS
   (Let's Encrypt) yourself, and need a domain or a free dynamic-DNS name.
   Oracle reclaims Always Free instances that stay idle (low CPU and network
   for 7 days), which a lightly used app can trigger, so keep backups.
3. **Low-cost paid shared hosting** that allows cURL. For real client
   records with no free-tier support or backups, this is often the safer
   choice.

Whatever you pick: the database holds NICs and phone numbers, so keep regular
backups and use a host you trust.

## Backup

- **Database**: `mysqldump -u <user> -p nithi_docket > backup-$(date +%F).sql`
- **Restore**: `mysql -u <user> -p nithi_docket < backup-YYYY-MM-DD.sql`
- Keep copies of `.env` and `firebase/service-account.json` somewhere safe and
  private; they are not in git.

Nothing here schedules backups automatically. Set up a cron job or your
host's managed backups.

## SMS credit

Each SMS bills per part (Sinhala: 70 characters, or 67 per part when longer).
The default two-language message is about 335 characters, which is 5 or 6
parts per recipient. If the text.lk balance is too low the send fails, the
deed page says so in red, and staff can use **Resend SMS** after topping up.

## Not built (deliberate)

- No CI pipeline, queue worker or zero-downtime tooling: unnecessary at this
  scale.
- No document upload feature.
