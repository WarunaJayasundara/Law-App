<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class FcmToken
{
    public static function register(int $userId, string $token, string $deviceType = 'web'): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO fcm_tokens (user_id, token, device_type) VALUES (:user_id, :token, :device_type)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_type = VALUES(device_type), updated_at = NOW()'
        );
        $stmt->execute(['user_id' => $userId, 'token' => $token, 'device_type' => $deviceType]);
    }

    public static function remove(string $token): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM fcm_tokens WHERE token = :token');
        $stmt->execute(['token' => $token]);
    }

    /** @return string[] */
    public static function tokensForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT token FROM fcm_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
