# Firebase Cloud Messaging setup

Nithi Docket uses Firebase Cloud Messaging (FCM) for push notifications
only — not SMS (see the SMS note at the bottom). Until you complete this
setup, every notification attempt is recorded honestly as "not configured"
instead of pretending to succeed.

## 1. Create a Firebase project

1. Go to https://console.firebase.google.com and create a new project (or
   reuse an existing one).
2. You do not need Analytics for this project — you can disable it.

## 2. Register a web app

1. In the Firebase console, open **Project settings** → **General** →
   **Your apps** → **Add app** → Web (`</>`).
2. Give it a nickname (e.g. "Nithi Docket web").
3. Copy the generated `firebaseConfig` object — you'll need it for step 5.

## 3. Enable Cloud Messaging

1. In **Project settings** → **Cloud Messaging**, confirm the Cloud
   Messaging API (V1) is enabled.
2. Under **Web configuration**, generate a **Web Push certificate**
   (VAPID key pair). Copy the public key — you'll need it for step 5.

## 4. Create server credentials (for sending)

1. **Project settings** → **Service accounts** → **Generate new private
   key**. This downloads a JSON file.
2. Save it as `firebase/service-account.json` in this project (already
   gitignored — never commit it).
3. In `.env`, set:
   ```
   FIREBASE_PROJECT_ID=<your-project-id>
   FIREBASE_SERVICE_ACCOUNT_PATH=firebase/service-account.json
   ```
4. That's it on the backend — `FirebaseNotificationService` builds its own
   signed OAuth2 token from this file using PHP's `openssl` extension, no
   Firebase Admin SDK/Composer package required.

## 5. Configure the browser client (for registering device tokens)

1. Open `public/assets/js/firebase-config.js` (copy from
   `firebase-config.example.js`) and fill in the `firebaseConfig` object
   from step 2 and the VAPID key from step 3.
2. **Also** copy `public/firebase-messaging-sw.example.js` to
   `public/firebase-messaging-sw.js` (same config values) — this is a
   required service worker, not optional. Web push delivery goes through
   the browser's Push API, which only works via a registered service
   worker; without this file at the site root, `getToken()` fails even
   with an otherwise-correct config.
3. Until these files exist with real values, the "enable notifications"
   flow quietly does nothing — it does not throw errors or pretend to
   register a device.
4. Once configured, a logged-in user who accepts the browser's
   notification permission prompt gets a device token registered against
   their account via `POST /api/fcm/register-token` — which also
   subscribes that token to the relevant topics server-side (see below;
   this step is required and easy to miss).

## 6. Subscribing to topics

This app sends targeted broadcasts to **topics**
(`all-users`, `staff`, `buyers`, `sellers`, `announcements`,
`deed-updates`). The Firebase Web SDK has no client-side
`subscribeToTopic()` — that's Android/iOS-only — so subscription happens
**server-side**, via Google's Instance ID REST API
(`FirebaseNotificationService::subscribeToTopic()`, using the same
service-account OAuth2 token as sending). `NotificationController::registerToken()`
calls this automatically right after storing a new device token: every
browser gets subscribed to `all-users`, `announcements`, and
`deed-updates`, plus `staff` if the account's role is `staff`. `buyers`
and `sellers` have no real subscribers by design — those parties don't
have app accounts, so a send to those topics will succeed at the API
level but reach nobody (a real, documented gap — see the SMS note below
for the actual way to reach buyers/sellers).

## 7. Testing

1. Log in as a user with `notifications.send` permission.
2. Go to **Notifications** → send a test notification to a topic.
3. Check the **History** table — `status` will be `sent` on success or
   `failed` with the real error message from Firebase otherwise.

## SMS is a separate concern, now wired up via text.lk

FCM is push notification, not SMS — it can't reach a buyer/seller's phone,
since they never open this app at all. The per-deed "Notify buyer and
seller" action SMSes them via a real `TextLkSmsNotificationService`
(`app/Services/Notifications/`), implementing `SmsNotificationServiceInterface`.
See `docs/SMS_SETUP.md` for text.lk configuration.
