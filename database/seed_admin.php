<?php

declare(strict_types=1);

/**
 * One-time CLI script: creates the default admin account from the
 * SEED_ADMIN_* values in .env. Safe to re-run — does nothing if a user
 * with that username or email already exists. Run from the project root:
 *   php database/seed_admin.php
 * Rotate/change the password immediately after first login in production.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$name = Env::get('SEED_ADMIN_NAME', 'System Administrator');
$email = Env::get('SEED_ADMIN_EMAIL', 'admin@example.com');
$username = Env::get('SEED_ADMIN_USERNAME', 'admin');
$password = Env::get('SEED_ADMIN_PASSWORD', '');

if ($password === '') {
    fwrite(STDERR, "SEED_ADMIN_PASSWORD is not set in .env — aborting.\n");
    exit(1);
}

if (Env::get('APP_ENV') === 'production') {
    $problem = \App\Services\PasswordPolicy::check($password, [$username, $email]);
    if ($problem !== null) {
        fwrite(STDERR, "SEED_ADMIN_PASSWORD is not acceptable for production: {$problem}\n");
        exit(1);
    }
}

$db = Database::connection();

$check = $db->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
$check->execute(['u' => $username, 'e' => $email]);
if ($check->fetch()) {
    echo "Admin account already exists ({$username}) — nothing to do.\n";
    exit(0);
}

$roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :name');
$roleStmt->execute(['name' => 'admin']);
$role = $roleStmt->fetch();
if (!$role) {
    fwrite(STDERR, "The 'admin' role doesn't exist — run database/nithi_docket.sql first.\n");
    exit(1);
}

$insert = $db->prepare(
    'INSERT INTO users (name, email, username, password_hash, role_id, status) VALUES (:name, :email, :username, :hash, :role_id, :status)'
);
$insert->execute([
    'name' => $name,
    'email' => $email,
    'username' => $username,
    'hash' => password_hash($password, PASSWORD_DEFAULT),
    'role_id' => $role['id'],
    'status' => 'active',
]);

echo "Admin account created: {$username} / {$email}\n";
echo "Log in, then rotate this password from the Users page.\n";
