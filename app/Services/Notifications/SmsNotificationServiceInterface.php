<?php

namespace App\Services\Notifications;

/**
 * Firebase Cloud Messaging is a push-notification system, not an SMS
 * gateway — it cannot send SMS. This interface exists so a real SMS
 * provider (e.g. a local Sri Lankan SMS gateway) can be plugged in later
 * without touching call sites, matching the project's swappable-service
 * pattern. No implementation is wired up (needs a provider + credentials).
 */
interface SmsNotificationServiceInterface
{
    /** @return array{ok:bool, error:?string} */
    public function send(string $mobileNumber, string $message): array;
}
