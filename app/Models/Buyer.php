<?php

namespace App\Models;

use App\Core\Database;

final class Buyer
{
    /** Finds an existing buyer by NIC, or creates one — mirrors the prototype's inline buyer entry. */
    public static function findOrCreate(string $fullName, string $nic, string $mobile, string $email = ''): int
    {
        $db = Database::connection();

        if ($nic !== '') {
            $stmt = $db->prepare('SELECT id FROM buyers WHERE nic = :nic LIMIT 1');
            $stmt->execute(['nic' => $nic]);
            $existing = $stmt->fetch();
            if ($existing) {
                $update = $db->prepare('UPDATE buyers SET full_name = :name, mobile = :mobile, email = :email WHERE id = :id');
                $update->execute(['name' => $fullName, 'mobile' => $mobile, 'email' => $email ?: null, 'id' => $existing['id']]);
                return (int) $existing['id'];
            }
        }

        $stmt = $db->prepare('INSERT INTO buyers (full_name, nic, mobile, email) VALUES (:name, :nic, :mobile, :email)');
        $stmt->execute([
            'name' => $fullName,
            'nic' => $nic !== '' ? $nic : uniqid('nic_'),
            'mobile' => $mobile ?: null,
            'email' => $email ?: null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM buyers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
