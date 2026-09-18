<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }
    }
}
