<?php

namespace App\Services\Notifications;

use App\Core\Env;
use App\Core\Logger;

/**
 * Real Firebase Cloud Messaging (HTTP v1 API) integration — no SDK
 * dependency, since a service-account-signed OAuth2 bearer token can be
 * built with PHP's built-in openssl functions alone (RS256 JWT).
 *
 * Requires FIREBASE_PROJECT_ID and a real service-account JSON at
 * FIREBASE_SERVICE_ACCOUNT_PATH (see /firebase/SETUP.md). Until those are
 * configured, every send returns ok=false with an explicit
 * "not configured" error — this class never pretends a message was
 * delivered when it wasn't.
 */
final class FirebaseNotificationService implements NotificationServiceInterface
{
    private ?array $serviceAccount;
    private ?string $projectId;

    public function __construct()
    {
        $this->projectId = Env::get('FIREBASE_PROJECT_ID') ?: null;
        $this->serviceAccount = $this->loadServiceAccount();
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        return $this->send(['topic' => $topic], $title, $body, $data);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens === []) {
            return ['ok' => false, 'recipientCount' => 0, 'error' => 'No registered device tokens for this recipient.'];
        }

        $delivered = 0;
        $lastError = null;

        foreach ($tokens as $token) {
            $result = $this->send(['token' => $token], $title, $body, $data);
            if ($result['ok']) {
                $delivered++;
            } else {
                $lastError = $result['error'];
            }
        }

        if ($delivered === 0) {
            return ['ok' => false, 'recipientCount' => 0, 'error' => $lastError ?? 'Delivery failed.'];
        }

        return ['ok' => true, 'recipientCount' => $delivered, 'error' => $delivered < count($tokens) ? $lastError : null];
    }

    /**
     * Subscribes a device token to a topic server-side, via the Instance ID
     * REST API. This is required for web push: the Firebase JS SDK has no
     * client-side subscribeToTopic() method (that's Android/iOS-only), so
     * without this call, sendToTopic() reaches zero real devices even when
     * Firebase itself accepts the send.
     *
     * @return array{ok:bool, error:?string}
     */
    public function subscribeToTopic(string $token, string $topic): array
    {
        if (!$this->projectId || !$this->serviceAccount) {
            return ['ok' => false, 'error' => 'Firebase is not configured. See /firebase/SETUP.md.'];
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return ['ok' => false, 'error' => 'Could not authenticate with Firebase.'];
        }

        $ch = curl_init('https://iid.googleapis.com/iid/v1:batchAdd');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                // Without this, the IID API assumes the Authorization header
                // is a legacy server key and rejects a real OAuth2 token.
                'access_token_auth: true',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'to' => '/topics/' . $topic,
                'registration_tokens' => [$token],
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('FCM topic subscribe failed (network): ' . $curlError);
            return ['ok' => false, 'error' => 'Network error contacting Firebase.'];
        }
        if ($status < 200 || $status >= 300) {
            Logger::error("FCM topic subscribe failed (HTTP {$status}): {$response}");
            return ['ok' => false, 'error' => 'Firebase rejected the subscription (HTTP ' . $status . ').'];
        }

        return ['ok' => true, 'error' => null];
    }

    private function send(array $target, string $title, string $body, array $data): array
    {
        if (!$this->projectId || !$this->serviceAccount) {
            return ['ok' => false, 'recipientCount' => null, 'error' => 'Firebase is not configured. See /firebase/SETUP.md.'];
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return ['ok' => false, 'recipientCount' => null, 'error' => 'Could not authenticate with Firebase.'];
        }

        $message = array_merge($target, [
            'notification' => ['title' => $title, 'body' => $body],
        ]);
        // FCM requires `data` to be a JSON object; an empty PHP array
        // encodes as `[]` (a list) and is rejected — omit it when empty.
        if ($data !== []) {
            $message['data'] = array_map('strval', $data);
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['message' => $message]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('FCM send failed (network): ' . $curlError);
            return ['ok' => false, 'recipientCount' => null, 'error' => 'Network error contacting Firebase.'];
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'recipientCount' => 1, 'error' => null];
        }

        Logger::error("FCM send failed (HTTP {$status}): {$response}");
        return ['ok' => false, 'recipientCount' => null, 'error' => 'Firebase rejected the notification (HTTP ' . $status . ').'];
    }

    private function loadServiceAccount(): ?array
    {
        $path = Env::get('FIREBASE_SERVICE_ACCOUNT_PATH');
        if (!$path) {
            return null;
        }
        $fullPath = dirname(__DIR__, 3) . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
            return null;
        }
        $json = json_decode(file_get_contents($fullPath), true);
        if (!is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
            return null;
        }
        return $json;
    }

    /**
     * OAuth2 JWT-bearer flow: sign a short-lived claim set with the service
     * account's RSA private key, exchange it for an access token. No
     * external JWT library needed — RS256 is a direct openssl_sign call.
     */
    private function getAccessToken(): ?string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";
        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $this->serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        if (!$signed) {
            Logger::error('FCM JWT signing failed.');
            return null;
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        // A cold curl handle occasionally fails the first HTTPS connection
        // (observed in testing as HTTP 0 with no response) — one retry
        // after a short pause resolves it without masking a real failure,
        // since a genuine credential/network problem still fails twice.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]),
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200) {
                $decoded = json_decode($response, true);
                return $decoded['access_token'] ?? null;
            }

            Logger::error("FCM OAuth token exchange failed, attempt {$attempt} (HTTP {$status}): {$response}");
            if ($attempt === 1) {
                usleep(300000);
            }
        }

        return null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
