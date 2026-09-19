<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    /** Pages still reachable while a forced password change is pending. */
    private const ALLOWED_WHILE_FORCED = ['/account', '/account/password', '/logout'];

    public static function handle(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }

        if (Session::get('force_password_change') && !in_array(Request::path(), self::ALLOWED_WHILE_FORCED, true)) {
            Response::redirect('/account');
        }
    }
}
