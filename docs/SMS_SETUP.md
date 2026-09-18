# SMS setup (text.lk)

Real text messages to buyer/seller mobile numbers, via
[text.lk](https://text.lk). This is a completely separate system from
Firebase — see `firebase/SETUP.md`'s note on why FCM can't do this.

## 1. Create a text.lk account

1. Sign up at [text.lk](https://text.lk).
2. Register a **Sender ID** — the name recipients see as who the SMS is
   from. This typically needs text.lk's approval before it's usable; start
   this early if you haven't already.

## 2. Get your API token

1. In your text.lk dashboard, find your **API Token** (under
   API/Developer settings).
2. In `.env`, set:
   ```
   TEXTLK_API_TOKEN=<your-token>
   TEXTLK_SENDER_ID=<your-approved-sender-id>
   ```
3. That's it — `TextLkSmsNotificationService` builds the request directly
   against text.lk's documented API
   (`POST https://app.text.lk/api/v3/sms/send`), no SDK needed.

## How it's used

The per-deed **"Notify buyer and seller"** button (available once a deed
is marked Received) sends a real SMS to both the buyer's and seller's
mobile numbers on file, independently reporting success/failure for each.
Every attempt is recorded in the audit log
(`deed.sms_attempted`/`deed.notification_sent`) regardless of outcome.

## Phone number format

Numbers are stored locally as entered (e.g. `0771234567`).
`TextLkSmsNotificationService::toInternationalFormat()` converts to the
international format text.lk expects (`94771234567`) and only accepts
real Sri Lankan **mobile** prefixes (7X) — a landline number (e.g. an
`011...` Colombo number) is correctly rejected with a clear error, since
SMS can't reach a landline.

## Testing

Until `TEXTLK_API_TOKEN`/`TEXTLK_SENDER_ID` are set, every send attempt
returns `ok: false, error: "SMS gateway (text.lk) is not configured."` —
honest, not a fake success. Once configured, test with a real deed marked
Received and a real mobile number on file, then check the flash message
on the deed page (reports each party's SMS result) and the Audit Log for
the full attempt record.

**Current state of this deployment**: `TEXTLK_SENDER_ID` is set to
`TextLKDemo`, text.lk's built-in testing sender ID — no approval needed,
but per their own terms it must not be used for production messages.
Verified with a real send to a real number (API returned `ok: true`).
**Before going live, replace it with a real, approved Sender ID** — see
"How to Request a Custom Sender ID" above (usually a few hours to 1
business day for approval). It's a one-line `.env` change, nothing else
needs to be touched.

## Cost

text.lk is a paid service (per-SMS pricing) once past any trial credit —
check their pricing before sending in volume. This project doesn't
implement any rate limiting or spend cap on SMS sending; the only gate is
that a deed must be marked Received first, and the button disables itself
after the first successful notification per deed.
