<?php

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip the app's base path (e.g. /nithi-docket/public) so routes stay clean.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return '/' . ltrim($path, '/');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function trimmed(string $key, string $default = ''): string
    {
        return trim((string) self::input($key, $default));
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    public static function csrfToken(): ?string
    {
        return $_POST['_csrf'] ?? null;
    }

    /**
     * Only when TRUST_PROXY=true (the app sits behind a reverse proxy or
     * host-provided load balancer that sets these headers) are the forwarded
     * headers believed. The rightmost X-Forwarded-For entry is the address
     * the trusted proxy itself saw, so it cannot be forged by the client.
     */
    public static function trustsProxy(): bool
    {
        return Env::get('TRUST_PROXY', 'false') === 'true';
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        return self::trustsProxy() && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function ip(): string
    {
        if (self::trustsProxy() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $hops = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $candidate = end($hops);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    public static function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    }

    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }
}
