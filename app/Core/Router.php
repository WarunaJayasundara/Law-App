<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        // HTML forms can't send DELETE; routes are POSTed with _method=DELETE.
        $this->add('POST', $path, $handler, $middleware, true);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware, bool $isDeleteAlias = false): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
            'isDeleteAlias' => $isDeleteAlias,
        ];
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();

        if ($method === 'POST' && Request::input('_method') === 'DELETE') {
            $method = 'DELETE';
        }

        foreach ($this->routes as $route) {
            $routeMethod = $route['isDeleteAlias'] ? 'DELETE' : $route['method'];
            if ($routeMethod !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $middleware) {
                $middleware();
            }

            // CSRF check for every state-changing request handled through the router.
            if (in_array($method, ['POST', 'DELETE'], true)) {
                if (!Csrf::verify(Request::csrfToken())) {
                    http_response_code(419);
                    View::render('errors/419', [], false);
                    return;
                }
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action($params);
                return;
            }
            $handler($params);
            return;
        }

        Response::notFound();
    }
}
