<?php

namespace App\Core;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        // HEX flags keep <, >, & and quotes out of the payload as literal characters, so it stays inert even if mis-sniffed as HTML.
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . self::url($path));
        exit;
    }

    public static function url(string $path): string
    {
        $base = rtrim(Env::get('APP_URL', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', [], false);
        exit;
    }

    public static function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', [], false);
        exit;
    }
}
