<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public static function findByLogin(string $usernameOrEmail): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = :login1 OR u.email = :login2
             LIMIT 1'
        );
        $stmt->execute(['login1' => $usernameOrEmail, 'login2' => $usernameOrEmail]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findWithRole(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.id, u.name, u.email, u.username, u.status, u.last_login_at, r.name AS role_name, r.id AS role_id
             FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.name'
        );
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, username, password_hash, role_id, status)
             VALUES (:name, :email, :username, :password_hash, :role_id, :status)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id' => $data['role_id'],
            'status' => $data['status'] ?? 'active',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateRoleAndStatus(int $id, int $roleId, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET role_id = :role_id, status = :status WHERE id = :id'
        );
        $stmt->execute(['role_id' => $roleId, 'status' => $status, 'id' => $id]);
    }

    public static function recordLoginSuccess(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function recordLoginFailure(int $id): void
    {
        // MySQL evaluates a multi-assignment SET clause left-to-right, so by
        // the time `locked_until` is evaluated, `failed_login_attempts` on
        // the right-hand side already reflects the increment just applied
        // above — adding +1 again here was double-counting and locked the
        // account out one failure early (after 4, not 5).
        $stmt = Database::connection()->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1,
             locked_until = IF(failed_login_attempts >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function isLocked(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
    }

    public static function roles(): array
    {
        return Database::connection()->query('SELECT id, name FROM roles ORDER BY name')->fetchAll();
    }

    /** @return string[] permission names for a role, re-read fresh (not cached) every call. */
    public static function permissionsFor(int $roleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.name FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id'
        );
        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
