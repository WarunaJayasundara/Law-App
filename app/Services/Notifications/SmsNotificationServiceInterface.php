<?php

namespace App\Services\Notifications;

/**
 * Firebase Cloud Messaging is a push-notification system, not an SMS
 * gateway, so real SMS goes through a separate provider. The only
 * implementation is TextLkSmsNotificationService (text.lk); the interface
 * keeps call sites independent of the provider.
 */
interface SmsNotificationServiceInterface
{
    /**
     * `units` is the number of SMS parts the provider billed, when known.
     *
     * @return array{ok:bool, error:?string, units?:?int}
     */
    public function send(string $mobileNumber, string $message): array;
}
