<?php

namespace App\Services\Notifications;

interface NotificationServiceInterface
{
    /**
     * @return array{ok:bool, recipientCount:?int, error:?string}
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array;

    /**
     * @param string[] $tokens
     * @return array{ok:bool, recipientCount:?int, error:?string}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array;
}
