// Registers the current browser for push notifications via Firebase
// Cloud Messaging, IF window.NITHI_FIREBASE_CONFIG has been filled in
// (see /firebase/SETUP.md and firebase-config.example.js). Otherwise this
// silently does nothing — it never fakes a successful registration.
(function () {
  var config = window.NITHI_FIREBASE_CONFIG;
  var vapidKey = window.NITHI_FIREBASE_VAPID_KEY;

  if (!config || config.apiKey === "REPLACE_ME" || !vapidKey || vapidKey === "REPLACE_ME") {
    return;
  }
  if (!("serviceWorker" in navigator) || !("Notification" in window)) {
    return;
  }

  var script1 = document.createElement("script");
  script1.src = "https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js";
  script1.onload = function () {
    var script2 = document.createElement("script");
    script2.src = "https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js";
    script2.onload = initMessaging;
    document.head.appendChild(script2);
  };
  document.head.appendChild(script1);

  function initMessaging() {
    firebase.initializeApp(config);
    var messaging = firebase.messaging();

    // FCM web push delivery requires a registered service worker — without
    // one, getToken() rejects even though everything else is configured.
    navigator.serviceWorker
      .register("/firebase-messaging-sw.js")
      .then(function (registration) {
        return Notification.requestPermission().then(function (permission) {
          if (permission !== "granted") return null;
          return messaging.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: registration });
        });
      })
      .then(function (token) {
        if (!token) return;
        var csrfInput = document.querySelector('input[name="_csrf"]');
        return fetch(window.location.origin + "/api/fcm/register-token", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: new URLSearchParams({
            token: token,
            device_type: "web",
            _csrf: csrfInput ? csrfInput.value : "",
          }),
        });
      })
      .catch(function (err) {
        console.error("Nithi Docket: push notification setup failed.", err);
      });
  }
})();
