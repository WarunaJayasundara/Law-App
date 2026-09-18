<?php

namespace App\Models;

use App\Core\Database;

final class RegistrationDetail
{
    public static function find(int $deedId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM registration_details WHERE deed_id = :deed_id');
        $stmt->execute(['deed_id' => $deedId]);
        return $stmt->fetch() ?: null;
    }

    /** Plain data fields only — no longer drive the status (see markReviewed/markReceived). */
    public static function updateFields(int $deedId, array $data, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE registration_details SET
                register_date = :register_date,
                day_book_number = :day_book_number,
                new_folio_number = :new_folio_number,
                notes = :notes,
                updated_by = :updated_by
             WHERE deed_id = :deed_id'
        );
        $stmt->execute([
            'register_date' => $data['register_date'] ?: null,
            'day_book_number' => $data['day_book_number'] ?: null,
            'new_folio_number' => $data['new_folio_number'] ?: null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $userId,
            'deed_id' => $deedId,
        ]);
    }

    public static function markReviewed(int $deedId, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE registration_details SET reviewed = 1, reviewed_at = NOW(), reviewed_by = :user_id WHERE deed_id = :deed_id'
        );
        $stmt->execute(['user_id' => $userId, 'deed_id' => $deedId]);
    }

    public static function markReceived(int $deedId, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE registration_details SET received = 1, received_at = NOW(), received_by = :user_id WHERE deed_id = :deed_id'
        );
        $stmt->execute(['user_id' => $userId, 'deed_id' => $deedId]);
    }

    public static function markNotificationSent(int $deedId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE registration_details SET notification_sent = 1, notification_sent_at = NOW() WHERE deed_id = :deed_id'
        );
        $stmt->execute(['deed_id' => $deedId]);
    }
}
