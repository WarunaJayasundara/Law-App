<?php

declare(strict_types=1);

// Tests use the app's own lightweight autoloader — no DB connection is
// made unless a test explicitly calls Database::connection().
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    } elseif (str_starts_with($class, 'Tests\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    } else {
        return;
    }
    if (is_file($path)) {
        require $path;
    }
});
