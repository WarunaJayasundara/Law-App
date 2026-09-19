# SMS setup (text.lk)

Real text messages to buyer/seller mobile numbers, via
[text.lk](https://text.lk). This is a completely separate system from
Firebase — see `firebase/SETUP.md`'s note on why FCM can't do this.

## What is sent, and when

**One SMS per party, once, when a deed is marked Received.** Nothing is sent
at Submitted or Reviewed. It goes to the mobile numbers saved on the deed's
buyer and seller (visible and editable on the deed page). If both parties
share one number, it is sent once.

The wording is a template an **admin can edit** under **Settings**. Only
`{deed_number}` changes per deed. The default is the office's two-language
confirmation (English, then Sinhala). The Settings page shows a live preview
and how many SMS parts it will cost. Every save or reset is in the audit log.

## 1. Create a text.lk account and Sender ID

1. Sign up at [text.lk](https://text.lk). New accounts get a testing sender
   `TextLKDemo`, which their terms forbid for production messages.
2. Request your own Sender ID in the dashboard under **Sending → Sender ID**.
   Approval usually takes a few hours to one business day.
3. This deployment uses the approved Sender ID `YUSORA`.

## 2. Configure

In `.env`:

```
TEXTLK_API_TOKEN=<your-token>
TEXTLK_SENDER_ID=<your-approved-sender-id>
```

`TextLkSmsNotificationService` calls
`POST https://app.text.lk/api/v3/sms/send` directly. No SDK.

## Cost: read this

text.lk bills per SMS **part**. Sinhala needs Unicode encoding: **70
characters in one SMS, 67 per part once longer.** Verified against text.lk's
own responses: a 72-character Sinhala message was billed 2 units, and the
default two-language message (335 characters) is quoted as 5 units per
recipient. A longer deed number can push it to 6, because 335 sits exactly on a
boundary (5 x 67).

So a deed costs about 10 to 12 units when buyer and seller have different
numbers. Shorten the message under Settings to cut that. An English-only
message under 160 characters is 1 unit.

If the balance is too low, text.lk refuses the send (HTTP 403, "not enough
balance"). The deed page then shows the real reason in red, the status change
still stands, and **Resend SMS** works after topping up (one send per 60
seconds per deed).

## Success and failure are read from text.lk's answer

The service treats a send as delivered only when text.lk's response body says
`"status":"success"`, not merely because HTTP was 2xx. The number of billed
units comes from `data.sms_count` and is shown after a successful send.

## Phone number format

Numbers are stored as entered (e.g. `0764089523`).
`TextLkSmsNotificationService::toInternationalFormat()` converts to the
`94XXXXXXXXX` form and only accepts Sri Lankan **mobile** numbers (07X). A
landline (`011...`) is refused with a clear error, since SMS can't reach it.

## Testing

Until `TEXTLK_API_TOKEN` and `TEXTLK_SENDER_ID` are set, every send returns
`ok: false, "SMS gateway (text.lk) is not configured."` — never a fake
success. To test, mark a real deed Received with your own mobile number on it
and read the result at the top of the deed page and in the Audit log
(`deed.sms_attempted`). Use a short message while testing to save credit.
