<?php

namespace App\Models;

use App\Core\Database;
use App\Services\SmsTemplate;

/** Small key/value store for admin-editable settings. */
final class Setting
{
    public const RECEIVED_TEMPLATE = 'sms_received_template';

    public static function get(string $key): ?string
    {
        $stmt = Database::connection()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public static function set(string $key, string $value, ?int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (:k, :v, :u)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'u' => $userId]);
    }

    public static function delete(string $key): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM settings WHERE setting_key = :k');
        $stmt->execute(['k' => $key]);
    }

    public static function receivedTemplate(): string
    {
        return self::get(self::RECEIVED_TEMPLATE) ?? SmsTemplate::DEFAULT_RECEIVED;
    }

    public static function isReceivedTemplateCustomised(): bool
    {
        return self::get(self::RECEIVED_TEMPLATE) !== null;
    }
}
