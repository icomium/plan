<?php
declare(strict_types=1);

namespace App;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $re, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = '/' . trim($path, '/');
        $wrongMethod = false;
        foreach ($this->routes as [$m, $re, $handler]) {
            if (!preg_match($re, $path, $mt)) {
                continue;
            }
            if ($m !== $method) {
                $wrongMethod = true;
                continue;
            }
            $args = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler($args);
            return;
        }
        $code = $wrongMethod ? 405 : 404;
        render('error', ['code' => $code], $code);
    }
}
