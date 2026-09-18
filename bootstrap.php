<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Logger;

// Simple PSR-4-ish autoloader: App\Foo\Bar -> app/Foo/Bar.php.
// No Composer dependency for this project — kept intentionally lightweight.
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $path = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Env::load(__DIR__ . '/.env');

date_default_timezone_set('Asia/Colombo');

$debug = Env::get('APP_DEBUG', 'false') === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (\Throwable $e): void {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (Env::get('APP_DEBUG', 'false') === 'true') {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        echo 'Something went wrong. Please try again, or contact support if this continues.';
    }
});
