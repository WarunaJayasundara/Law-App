<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], bool $withLayout = true): void
    {
        $data['currentUser'] = Auth::user();
        View::render($view, $data, $withLayout);
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    protected function flashAndRedirect(string $type, string $message, string $path): void
    {
        Session::flash($type, $message);
        Response::redirect($path);
    }

    protected function requirePermission(string $permission): void
    {
        if (!Auth::can($permission)) {
            Response::forbidden();
        }
    }
}
