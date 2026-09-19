<?php

namespace App\Core;

/**
 * Response headers sent on every dynamic page. The CSP still allows inline
 * scripts/styles because the templates use a few inline handlers; it does
 * lock down where scripts, styles, fonts and connections may come from, and
 * blocks framing (clickjacking), plugins and <base>/form-action hijacking.
 */
final class SecurityHeaders
{
    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Content-Security-Policy: ' . self::csp());

        // Pages hold personal/legal data: never let a browser or proxy cache them
        // (also stops the Back button from showing them after logout).
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');

        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self' https://*.googleapis.com https://www.gstatic.com",
            "worker-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
