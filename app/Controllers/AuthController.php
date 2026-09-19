<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;

final class AuthController extends Controller
{
    /** Failed sign-ins allowed from one IP address inside the window, across all usernames. */
    private const MAX_FAILURES_PER_IP = 20;
    private const FAILURE_WINDOW_MINUTES = 10;

    /** A valid bcrypt hash of a random value; verified against when the username is unknown so timing doesn't reveal which usernames exist. */
    private const DUMMY_HASH = '$2y$10$Pm.EroN2Id94iCEesGLgW.NGkrAG2Oy.iVUgtJugQNYtginLl3N7S';

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
        $loggedLogin = mb_substr($login, 0, 100);

        if (AuditLog::countRecentFailuresFromIp(Request::ip(), self::FAILURE_WINDOW_MINUTES) >= self::MAX_FAILURES_PER_IP) {
            $this->flashAndRedirect('error', 'Too many failed sign-in attempts from your network. Please wait a few minutes and try again.', '/login');
        }

        $user = $login !== '' ? User::findByLogin($login) : null;

        if (!$user || $user['status'] !== 'active') {
            password_verify($password, self::DUMMY_HASH);
            AuditLogger::log('login.failed', 'user', $user['id'] ?? null, null, ['login' => $loggedLogin]);
            $this->flashAndRedirect('error', 'Incorrect username/email or password.', '/login');
        }

        if (User::isLocked($user)) {
            $this->flashAndRedirect('error', 'This account is temporarily locked after repeated failed attempts. Try again later.', '/login');
        }

        if (!password_verify($password, $user['password_hash'])) {
            User::recordLoginFailure($user['id']);
            AuditLogger::log('login.failed', 'user', $user['id'], null, ['login' => $loggedLogin]);
            $this->flashAndRedirect('error', 'Incorrect username/email or password.', '/login');
        }

        User::recordLoginSuccess($user['id']);
        Auth::login($user['id']);
        AuditLogger::log('login.success', 'user', $user['id']);

        // In production the published default password must be replaced before
        // anything else can be used (AuthMiddleware enforces this).
        if (Env::get('APP_ENV') === 'production' && PasswordPolicy::isKnownDefault($password, Env::get('SEED_ADMIN_PASSWORD'))) {
            Session::set('force_password_change', true);
            $this->redirect('/account');
        }

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
