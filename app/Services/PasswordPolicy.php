<?php

namespace App\Services;

/** Password rules shared by "create user" and "change my password". Pure logic. */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;
    /** bcrypt ignores everything past 72 bytes, so longer input is refused instead of silently truncated. */
    public const MAX_BYTES = 72;

    /** Passwords published in this project's own docs/.env.example — never acceptable. */
    private const KNOWN_DEFAULTS = ['ChangeMe123!'];

    public static function isKnownDefault(string $password, ?string $seedPassword = null): bool
    {
        if (in_array($password, self::KNOWN_DEFAULTS, true)) {
            return true;
        }
        return $seedPassword !== null && $seedPassword !== '' && hash_equals($seedPassword, $password);
    }

    /**
     * @param string[] $forbidden values the password must not equal (username, email, ...)
     * @return string|null an error message, or null when acceptable
     */
    public static function check(string $password, array $forbidden = [], ?string $seedPassword = null): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'The password must be at least ' . self::MIN_LENGTH . ' characters.';
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'The password must be ' . self::MAX_BYTES . ' bytes or fewer.';
        }
        if (self::isKnownDefault($password, $seedPassword)) {
            return 'That password is a published default. Choose your own.';
        }
        foreach ($forbidden as $value) {
            if ($value !== '' && strcasecmp($password, $value) === 0) {
                return 'The password must not be the same as your username or email.';
            }
        }
        return null;
    }
}
