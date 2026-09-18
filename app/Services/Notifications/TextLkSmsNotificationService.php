<?php

namespace App\Services\Notifications;

use App\Core\Env;
use App\Core\Logger;

/**
 * Real SMS via text.lk (https://text.lk/docs/send-sms/). API shape taken
 * directly from their published docs, not guessed:
 *   POST https://app.text.lk/api/v3/sms/send
 *   Authorization: Bearer <token>
 *   { recipient, sender_id, type: "plain", message }
 *
 * Requires TEXTLK_API_TOKEN and TEXTLK_SENDER_ID in .env. Until both are
 * set, send() returns ok=false with an explicit "not configured" error —
 * never a fake success.
 */
final class TextLkSmsNotificationService implements SmsNotificationServiceInterface
{
    private const ENDPOINT = 'https://app.text.lk/api/v3/sms/send';

    private ?string $apiToken;
    private ?string $senderId;

    public function __construct()
    {
        $this->apiToken = Env::get('TEXTLK_API_TOKEN') ?: null;
        $this->senderId = Env::get('TEXTLK_SENDER_ID') ?: null;
    }

    public function send(string $mobileNumber, string $message): array
    {
        if (!$this->apiToken || !$this->senderId) {
            return ['ok' => false, 'error' => 'SMS gateway (text.lk) is not configured.'];
        }

        $recipient = self::toInternationalFormat($mobileNumber);
        if ($recipient === null) {
            return ['ok' => false, 'error' => "Mobile number '{$mobileNumber}' doesn't look like a valid Sri Lankan number."];
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'recipient' => $recipient,
                'sender_id' => $this->senderId,
                'type' => 'plain',
                'message' => $message,
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error('text.lk send failed (network): ' . $curlError);
            return ['ok' => false, 'error' => 'Network error contacting text.lk.'];
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'error' => null];
        }

        Logger::error("text.lk send failed (HTTP {$status}): {$response}");
        $decoded = json_decode((string) $response, true);
        $apiMessage = is_array($decoded) ? ($decoded['message'] ?? null) : null;
        return ['ok' => false, 'error' => $apiMessage ?? ('text.lk rejected the message (HTTP ' . $status . ').')];
    }

    /**
     * Sri Lankan numbers are stored locally as 07XXXXXXXX; text.lk expects
     * international format with no leading + or 0 (94XXXXXXXXX). Also
     * accepts numbers already given as +94XXXXXXXXX or 94XXXXXXXXX.
     */
    public static function toInternationalFormat(string $mobileNumber): ?string
    {
        $digits = preg_replace('/\D/', '', $mobileNumber) ?? '';

        if (preg_match('/^0(7\d{8})$/', $digits, $m)) {
            return '94' . $m[1];
        }
        if (preg_match('/^94(7\d{8})$/', $digits)) {
            return $digits;
        }
        if (preg_match('/^(7\d{8})$/', $digits, $m)) {
            return '94' . $m[1];
        }

        return null;
    }
}
