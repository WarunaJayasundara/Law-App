<?php

namespace App\Models;

use App\Core\Database;

final class Notification
{
    public const TOPICS = ['all-users', 'staff', 'buyers', 'sellers', 'announcements', 'deed-updates'];

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (title, message, target_type, target_value, related_deed_id, sent_by, status)
             VALUES (:title, :message, :target_type, :target_value, :related_deed_id, :sent_by, :status)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'message' => $data['message'],
            'target_type' => $data['target_type'],
            'target_value' => $data['target_value'],
            'related_deed_id' => $data['related_deed_id'] ?? null,
            'sent_by' => $data['sent_by'],
            'status' => 'pending',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function markResult(int $id, string $status, ?string $error, ?int $recipientCount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notifications SET status = :status, error_message = :error, recipient_count = :count, sent_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'error' => $error, 'count' => $recipientCount, 'id' => $id]);
    }

    public static function history(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT n.*, u.name AS sent_by_name FROM notifications n
             JOIN users u ON u.id = n.sent_by
             ORDER BY n.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
