<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLogger;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', [
            'error' => Session::flash('error'),
            'csrf' => Csrf::field(),
        ], false);
    }

    public function login(): void
    {
        $login = Request::trimmed('login');
        $password = (string) Request::input('password', '');

        $user = $login !== '' ? User::findByLogin($login) : null;

        if (!$user || $user['status'] !== 'active') {
            AuditLogger::log('login.failed', 'user', $user['id'] ?? null, null, ['login' => $login]);
            $this->flashAndRedirect('error', 'Incorrect username/email or password.', '/login');
        }

        if (User::isLocked($user)) {
            $this->flashAndRedirect('error', 'This account is temporarily locked after repeated failed attempts. Try again later.', '/login');
        }

        if (!password_verify($password, $user['password_hash'])) {
            User::recordLoginFailure($user['id']);
            AuditLogger::log('login.failed', 'user', $user['id'], null, ['login' => $login]);
            $this->flashAndRedirect('error', 'Incorrect username/email or password.', '/login');
        }

        User::recordLoginSuccess($user['id']);
        Auth::login($user['id']);
        AuditLogger::log('login.success', 'user', $user['id']);

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        $userId = Auth::id();
        AuditLogger::log('logout', 'user', $userId);
        Auth::logout();
        $this->redirect('/login');
    }
}
