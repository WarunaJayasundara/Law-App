<?php

namespace App\Core;

final class Env
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    /** Real environment variables override .env (hosts that inject config); .env is the fallback. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $fromEnvironment = getenv($key);
        if ($fromEnvironment !== false) {
            return $fromEnvironment;
        }
        return self::$values[$key] ?? $default;
    }
}
