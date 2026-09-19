# Testing

## Automated (PHPUnit, no database required)

```bash
composer require --dev phpunit/phpunit:^9.6
php vendor/bin/phpunit
```

`tests/Unit/DeedStatusServiceTest.php` — the registration → status
derivation rule (Submitted / Reviewed / Received), driven by the
`reviewed`/`received` action flags rather than any data field.

`tests/Unit/ValidatorTest.php` — required-field, Sri Lankan NIC format
(old 9-digit+letter and new 12-digit), and decimal-amount validation.

These are deliberately scoped to pure logic with no I/O, so they run
without a database connection and stay fast. Controller/model behavior
that touches MySQL was instead verified by hand against a real running
instance (below) — this project doesn't use `RefreshDatabase`-style
integration tests, since there's no seeded test database in this
environment; if you add one, `Database::connection()` reads `DB_*` from
`.env` so pointing it at a disposable test schema is the natural next
step.

## Manual, live-verified during development (this is what actually ran)

Against a real MySQL 8 instance (via XAMPP), PHP's built-in dev server,
a real Firebase project, and a real text.lk account — in-browser and via
direct HTTP requests, not just code review:

- **Auth**: correct login, incorrect password, 5-strikes lockout (see
  "Found and fixed"), locked account correctly rejected even with the
  right password, successful login clears the lockout counter.
- **Deeds**: create (with inline buyer/seller find-or-create by NIC),
  view, edit details, search by buyer name/NIC/deed number/folio,
  category/status filtering, duplicate deed number correctly rejected
  (verified only one row exists in the DB after the attempt), archive
  (verified `is_archived` flips and the deed disappears from the list).
- **Registration workflow**: "Mark reviewed" correctly disabled once
  already reviewed, "Mark received" correctly disabled until reviewed
  (and disabled again once received), stepper UI correctly reflects each
  transition live end-to-end through the real browser UI.
- **Notifications — real delivery, not just "accepted by the API"**:
  Firebase push confirmed via a real send to a real project (OAuth2 token
  exchange + FCM accept), topic subscription confirmed via a real
  Instance ID API call, and real SMS confirmed via a real text.lk request
  to a real Sri Lankan mobile number (`ok: true` from their API). The
  manual "Notify buyer and seller" button of that time was exercised
  end-to-end through the actual UI ("Internal push: sent. Buyer SMS: sent.
  Seller SMS: sent."). It has since been replaced by the automatic
  one-time SMS on Received; see the section below for the current flow.
- **Users**: admin creates a `staff` account through the real form.
- **RBAC**: logged in as a real `staff` account — nav correctly hides
  Users/Audit log/Archive; *direct HTTP requests* (bypassing the UI
  entirely) to `/users`, `/audit-logs`, and a deed archive all correctly
  return 403, proving the checks are server-side.
- **CSRF**: a POST with no `_csrf` token correctly returns 419, not a
  silent pass-through.
- **Audit log**: every action above appears with the correct actor,
  action name, entity, and timestamp.
- **Responsive/visual**: mobile viewport (375px) checked on the deed
  detail page (stepper, action buttons, forms) and the deed registry list
  — no horizontal overflow, all controls usable.

### Found and fixed during this live testing (not just claimed)

**1. Duplicate PDO placeholders** — MySQL's native prepared statements
(`PDO::ATTR_EMULATE_PREPARES = false`) reject a named placeholder used
more than once in the same query. Only fails at *runtime*, invisible to
`php -l` or PHPUnit's pure-logic tests. Four instances found and fixed:
`User::findByLogin()` (`:login` reused), `Deed::search()` (`:q` reused 6
times), `Deed::create()` (`:created_by` reused for `updated_by` too — a
real correctness bug, not just a duplicate-placeholder one), and
`RegistrationDetail::update()` (`:report_received` reused, before that
method was later restructured).

**2. Firebase service-account path resolution** — `.env`'s
`FIREBASE_SERVICE_ACCOUNT_PATH` had a leading `../` that, combined with
the code's own base-path resolution, pointed one directory *above* the
project root. Found by directly testing `loadServiceAccount()` and
observing it return `null` despite a real, correctly-placed key file.

**3. FCM rejects an empty `data` field** — an empty PHP array encodes as
JSON `[]` (a list); FCM's API requires `data` to be an object and
rejected the request with `Cannot bind a list to map for field 'data'`.
Found via a real send attempt, fixed by omitting the field when empty.

**4. FCM's Instance ID API silently expects a legacy auth format** — topic
subscription calls returned `HTTP 401: Authentication using server key is
deprecated` even with a valid OAuth2 bearer token, until adding the
undocumented-in-obvious-places `access_token_auth: true` header.

**5. Login lockout was off by one** — `recordLoginFailure()`'s SQL set
`failed_login_attempts = failed_login_attempts + 1` and, in the same SET
clause, checked `IF(failed_login_attempts + 1 >= 5, ...)`. MySQL
evaluates a multi-assignment SET clause left-to-right, so the second
expression saw the *already-incremented* value and added 1 again —
locking the account after 4 failures instead of 5. Found by directly
calling `User::recordLoginFailure()` in a loop and checking the DB after
each call; fixed by removing the redundant `+ 1`, then re-verified the
same way (locks exactly on failure 5, not 4).

Each of these fails only when actually exercised — none would show up
from reading the code carefully, which is why every feature in this
project was checked by really running it, not just written and assumed
correct.

## UI modernization pass (mobile-first patterns)

Added a standard collapsible hamburger nav (below 900px) and a standard
responsive-table pattern (stacked labeled cards below 700px, via
`data-label` attributes + a `.nd-table-stack` CSS rule) across every data
table — deed registry, dashboard recent deeds, Users, Notifications
history, Audit log — instead of relying on horizontal scroll. Also added
double-submit prevention (disables a form's submit button right after
click) and a loading-spinner state.

**Found and fixed while verifying this**: `document.elementFromPoint()`
confirmed the hamburger button's hit-testing and DOM structure were
correct even when one round of automated-browser coordinate clicks missed
it — a testing-tool precision limit for small corner targets, not an app
bug, but the touch target was still genuinely undersized (40×40) for
comfortable real-world mobile tapping, so it was bumped to 44×44 (the
standard minimum) regardless. Separately, a real edge case was checked
deliberately, not just assumed: if a confirm() dialog (e.g. "Archive this
deed?") is cancelled, `event.defaultPrevented` is already true by the
time the double-submit-prevention script runs, and it correctly skips
disabling the button — verified by simulating a cancelled `confirm()` and
checking the button stayed enabled, so users can't get a button
permanently stuck disabled by backing out of a confirmation.

Full deed lifecycle (create → review → receive → notify, with real
Firebase push + real text.lk SMS) was re-run end-to-end after these
changes to confirm nothing broke — same "Internal push: sent. Buyer SMS:
sent. Seller SMS: sent." result as before.

## Editable one-time SMS, security hardening and Apache verification

The confirmation SMS is sent **once, when a deed is marked Received** (an
earlier iteration sent at every stage; that was removed). The wording is an
admin-editable template (Settings), and only `{deed_number}` varies.

### Automated (PHPUnit, 38 tests)

`SmsTemplateTest` (validation, rendering, GSM-7 and Unicode part counting,
including text.lk's own figures: 72 Sinhala characters = 2 parts, the default
335-character message = 5), `PasswordPolicyTest`, `TextLkPhoneFormatTest`
(local/international/spaced numbers accepted; landlines and junk rejected),
plus new `Validator` cases (deed-number characters, byte length).

### Real SMS (text.lk, sender `YUSORA`, to the developer's own number)

- A bilingual Sinhala + English probe showed text.lk accepts Sinhala through
  the normal `plain` type and bills it as Unicode. A 72-character Sinhala
  message returned `"status":"success"`, `sms_count: 2`. The full default
  message was refused with HTTP 403 "not enough balance ... 4 of the 5", which
  is how the 5-unit cost was confirmed.
- End to end through the app with a short custom message: create deed, mark
  reviewed (0 SMS sent), mark received: "Buyer SMS: sent (1 SMS units). Seller:
  same number as the buyer, so one SMS was sent."
- With the default message and too little credit: "Marked received ... Buyer
  SMS: not delivered (There is not enough balance ...)" in red, status still
  Received, and an immediate resend is refused for 60 seconds.
- A deed with no phone numbers reports "No mobile number on file" for both.

### Security tests run against the live app

- Unauthenticated requests to every page redirect to login; `.env`, the
  Firebase key, source, SQL and logs are not served.
- POST without a CSRF token: 419.
- Staff account: 403 on `/settings`, `/users`, `/audit-logs` and on POSTs to
  them (checked with direct requests, not just hidden links); the SMS template
  was unchanged after a staff attempt.
- Settings validation: no placeholder, unknown placeholder, and over-length
  templates rejected with nothing saved.
- Password change: wrong current, mismatch, same as current, published
  default, too short, equal to username: all refused; a valid change works,
  the old password stops working, and it is audit-logged.
- Lockout: locks on exactly the 5th wrong password, correct password refused
  while locked; logout ends the session; session ID rotates on login.
- Per-IP throttle: after 20 failures from one address the 21st attempt is
  refused even with the correct password; another address is unaffected.
- Behind a proxy (`TRUST_PROXY=true`): the rightmost `X-Forwarded-For` entry is
  recorded (a client-supplied `6.6.6.6` is ignored); HSTS and the `Secure`
  cookie appear only when the proxy says HTTPS.
- Production mode: signing in with the published default password redirects
  every page to My account until it is changed.
- Input: a URL as deed number rejected; `<script>` and `onerror` payloads in a
  name are escaped on every page; SQL-injection probes on search and suggest
  return normal empty results; editing a deed number to an existing one now
  gives a message (it was a 500).
- Security headers and a CSP are present, and the browser console showed no CSP
  violations on the deed, settings and dashboard pages.

### Real Apache (XAMPP, project folder as web root, PHP 8.0.11)

All 20 private paths (`.env`, `firebase/service-account.json`, `app/`,
`database/`, `storage/logs/`, `.git/`, `vendor/`, `composer.json`,
`.htaccess`, ...) return 403; four path-traversal variants fail; CSS, JS and
the service worker are served with correct types; deep routes work; login and
every page work.

### Bugs found and fixed in this pass

1. A blank line before `<?php` in `NotificationServiceInterface.php` emitted
   output early and could break redirects (headers already sent).
2. Editing a deed number to an existing one crashed with a database error.
3. The audit log recorded the raw form, including the session CSRF token.
4. The Apache instructions claimed no rewrite rules were needed; on Apache
   every URL but `/` would have been a 404.
5. No `.htaccess` existed, so on shared hosting `.env` and the Firebase private
   key would have been downloadable.
6. Account lockout compared MySQL and PHP clocks; on a UTC MySQL host it would
   silently never lock. The DB session timezone is now aligned with the app.
7. Buyer/seller phone numbers could not be seen or corrected after entry, so a
   typo would misdirect the SMS. They are now editable on the deed page, and an
   empty number no longer erases an existing one.
8. Auto-generated placeholder NICs (`nic_...`) were shown as real NICs.
9. No way to change the published default admin password inside the app.
10. Two of my own edits introduced errors that lint or a test caught before
    they shipped (an unescaped SQL quote in a PHP string, an undefined
    variable).

## Live search suggestions + more UI polish

The deed registry's search box now suggests matches as you type (debounced
220ms, `GET /deeds/suggest?q=...` — same fields as the full search: deed
no., NIC, buyer/seller name — capped at 8 results), with arrow-key
navigation, Enter to open, and click-outside/Escape to dismiss. Clicking a
suggestion jumps straight to that deed's detail page. Live-verified:
typing "Wa" surfaced the 3 matching "Waruna Jayasundara" deeds, refining
to "Waruna J" (including the space) kept the same live-narrowed set,
clicking a result navigated straight to `/deeds/1234`.

Also: the Filter/Clear buttons now match the app's premium button
treatment (gradient primary + outlined secondary, both with icons), and
result cards get a very low-opacity (2.8%) brand-icon watermark in the
bottom-right corner so long/empty card space doesn't read as a dead gap.

## Not yet covered

- No automated feature/integration tests against a real DB (would need a
  disposable test schema + fixtures — not built this session). The bugs
  above were caught by ad hoc live testing instead.
- WhatsApp Business Platform integration was discussed and deliberately
  not built (Meta business verification + template approval overhead,
  not clearly worth it over SMS for this use case — see the session's
  own discussion, not repeated in docs since it was never started).
