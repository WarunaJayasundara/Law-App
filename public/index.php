<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

require dirname(__DIR__) . '/bootstrap.php';

Session::start();

$router = new Router();

// ---- Auth (public) ----
$router->get('/', function () {
    Response::redirect(Auth::check() ? '/dashboard' : '/login');
});
$router->get('/login', [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->post('/logout', [App\Controllers\AuthController::class, 'logout'], [[AuthMiddleware::class, 'handle']]);

// ---- Authenticated app ----
$auth = [[AuthMiddleware::class, 'handle']];

$router->get('/dashboard', [App\Controllers\DashboardController::class, 'index'], $auth);

$router->get('/deeds', [App\Controllers\DeedController::class, 'index'], $auth);
$router->get('/deeds/create', [App\Controllers\DeedController::class, 'showCreateForm'], $auth);
$router->post('/deeds', [App\Controllers\DeedController::class, 'store'], $auth);
$router->get('/deeds/{id}', [App\Controllers\DeedController::class, 'show'], $auth);
$router->post('/deeds/{id}/details', [App\Controllers\DeedController::class, 'updateDetails'], $auth);
$router->post('/deeds/{id}/registration', [App\Controllers\DeedController::class, 'updateRegistrationFields'], $auth);
$router->post('/deeds/{id}/review', [App\Controllers\DeedController::class, 'markReviewed'], $auth);
$router->post('/deeds/{id}/receive', [App\Controllers\DeedController::class, 'markReceived'], $auth);
$router->post('/deeds/{id}/notify', [App\Controllers\DeedController::class, 'sendNotification'], $auth);
$router->delete('/deeds/{id}', [App\Controllers\DeedController::class, 'archive'], $auth);

$router->get('/notifications', [App\Controllers\NotificationController::class, 'index'], $auth);
$router->post('/notifications', [App\Controllers\NotificationController::class, 'send'], $auth);
$router->post('/api/fcm/register-token', [App\Controllers\NotificationController::class, 'registerToken'], $auth);
$router->delete('/api/fcm/remove-token', [App\Controllers\NotificationController::class, 'removeToken'], $auth);

$router->get('/users', [App\Controllers\UserController::class, 'index'], $auth);
$router->post('/users', [App\Controllers\UserController::class, 'store'], $auth);
$router->post('/users/{id}', [App\Controllers\UserController::class, 'update'], $auth);

$router->get('/audit-logs', [App\Controllers\AuditLogController::class, 'index'], $auth);

$router->dispatch();
