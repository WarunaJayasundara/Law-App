<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;

/** "My account": every signed-in user can change their own password. */
final class AccountController extends Controller
{
    public function show(): void
    {
        $this->view('account/index', [
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
            'forced' => (bool) Session::get('force_password_change'),
        ]);
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        $id = (int) Auth::id();
        $current = (string) Request::input('current_password', '');
        $new = (string) Request::input('new_password', '');
        $confirm = (string) Request::input('confirm_password', '');

        if (!$user || User::isLocked($user)) {
            $this->flashAndRedirect('error', 'This account is temporarily locked. Try again later.', '/account');
        }

        if (!password_verify($current, $user['password_hash'])) {
            // Counts toward the same 5-strikes lockout as a failed login, so a
            // hijacked session cannot be used to guess the current password.
            User::recordLoginFailure($id);
            AuditLogger::log('password.change_failed', 'user', $id);
            $this->flashAndRedirect('error', 'Your current password is incorrect.', '/account');
        }

        if ($new !== $confirm) {
            $this->flashAndRedirect('error', 'The new password and its confirmation do not match.', '/account');
        }
        if (hash_equals($current, $new)) {
            $this->flashAndRedirect('error', 'The new password must be different from the current one.', '/account');
        }
        $problem = PasswordPolicy::check($new, [(string) $user['username'], (string) $user['email']], Env::get('SEED_ADMIN_PASSWORD'));
        if ($problem !== null) {
            $this->flashAndRedirect('error', $problem, '/account');
        }

        User::updatePassword($id, $new);
        User::recordLoginSuccess($id);
        Session::regenerate();
        Session::remove('force_password_change');
        AuditLogger::log('password.changed', 'user', $id);

        $this->flashAndRedirect('success', 'Your password was changed.', '/account');
    }
}
