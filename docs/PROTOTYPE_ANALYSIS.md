# Prototype analysis

Source: `nithi-docket (2).html` — a single-file vanilla-JS SPA storing all
data in `localStorage`.

## Screens

1. **Auth** (login / signup) — plaintext password array in localStorage,
   compared client-side.
2. **Dashboard** — 3 stat tiles (total / pending / completed) + up-to-8
   recent deeds table.
3. **Deed registry** — search (deed no. / buyer NIC / seller NIC) + list +
   "Enter deed" modal.
4. **Deed detail** — inline-editable buyer/seller/deed fields, a
   registration-status panel (report received checkbox, register date, day
   book no., new folio no.), a "Send SMS to buyer and seller" button, and
   a delete-entry action.

## Fields (preserved exactly)

Buyer: full name, NIC/ID, mobile. Seller: full name, NIC/ID, mobile. Deed:
number, category (Transfer/Lease/Mortgage/Gift/Power of Attorney/Other),
folio no., amount, value, other document numbers. Registration: report
received, register date, day book no., new folio no., notification-sent
flag + log.

## Status rule (preserved exactly)

```
smsSent                       -> Completed
dayBookNumber && registerDate -> Registered
reportReceived                -> Report received
otherwise                     -> Submitted
```

Derived on every read in the prototype; in the rebuild this is computed by
`DeedStatusService` and *persisted* to `deeds.status` so it can be
indexed, filtered on, and can't silently disagree with what's displayed.

## Weaknesses identified (and how the rebuild addresses them)

| Weakness | Fix |
|---|---|
| All data in `localStorage` — no server, no backup, trivially wiped/edited by the user | MySQL, server-side only |
| Plaintext passwords, client-side comparison | `password_hash`/`password_verify`, server-side only |
| Open self-signup for a system holding NIC numbers and legal deed data | Removed; accounts are admin-created |
| "Send SMS" flips a boolean and fakes a log — no message is ever sent | Replaced with a real (Firebase push) + honestly-unconfigured (SMS) two-channel attempt that records the true outcome |
| No authorization boundaries | RBAC (`admin`/`staff`) with permission checks on every controller action, not just hidden UI |
| No audit trail | Added — every sensitive action logged with actor, entity, old/new values, IP |
| Implicit/derived status only, no server enforcement | Persisted, service-derived status; the derivation logic is unit-tested |

## What was deliberately *not* changed

- The 6 deed categories, exactly as listed.
- The overall visual identity (navy sidebar, warm paper background, brass
  accents, serif headings) — same design tokens, now as CSS custom
  properties shared across every page instead of one embedded
  `<style>` block.

## Revision: status model simplified further (post-launch feedback)

After the first working version above, the client asked for the status
workflow to be simpler and explicit rather than inferred from whether data
fields happened to be filled in. The rule changed from the prototype's
4-stage, field-driven derivation to a 3-stage, **action**-driven one:

```
Submitted -> Reviewed -> Received
```

`registration_details.reviewed`/`received` are now boolean flags set only
by an explicit "Mark reviewed"/"Mark received" button click (staff-driven,
audit-logged), not inferred from `report_received`/`day_book_number`/
`register_date` being present. Those three fields are now plain reference
data on the deed, not status triggers. Notifying the buyer/seller became
an independent courtesy action (available once Received) rather than a
4th status ("Completed") — matching the client's own framing that a
notification attempt shouldn't gate the deed's actual registration state.
Document upload/management was removed entirely at the same time — it
was never core to this workflow, so it was cut rather than kept as
half-used surface area.
