// Copy this file to firebase-messaging-sw.js (same folder, the site root)
// and fill in the same config as assets/js/firebase-config.js. Required
// for web push to actually deliver — see /firebase/SETUP.md.
importScripts("https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js");

firebase.initializeApp({
  apiKey: "REPLACE_ME",
  authDomain: "REPLACE_ME.firebaseapp.com",
  projectId: "REPLACE_ME",
  storageBucket: "REPLACE_ME.appspot.com",
  messagingSenderId: "REPLACE_ME",
  appId: "REPLACE_ME",
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
  const title = (payload.notification && payload.notification.title) || "Notification";
  const body = (payload.notification && payload.notification.body) || "";
  self.registration.showNotification(title, { body: body });
});
