<?php

namespace App\Core;

use App\Models\User;

/**
 * Thin per-request wrapper around the session's authenticated user.
 * Permissions are re-read from the DB each request (not cached in the
 * session) so a role/permission change takes effect immediately.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = self::id();
        if ($id === null) {
            return null;
        }

        self::$user = User::findWithRole($id);
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role_name'] ?? null;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return in_array($permission, User::permissionsFor((int) $user['role_id']), true);
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        self::$user = null;
        self::$resolved = false;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$resolved = false;
    }
}
