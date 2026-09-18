<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

final class PermissionMiddleware
{
    /** Returns a Router-compatible closure that checks a single permission. */
    public static function require(string $permission): callable
    {
        return static function () use ($permission): void {
            if (!Auth::can($permission)) {
                Response::forbidden();
            }
        };
    }
}
