<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal method + path router with {param} placeholders and per-route middleware.
 *
 *   $router->get('/api/products/{id}', [ProductController::class, 'show'], ['can:products.view']);
 */
final class Router
{
    /** @var array<int, array{method: string, path: string, regex: string, handler: array|callable, middleware: string[]}> */
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @return array{handler: array|callable, params: array<string, string>, middleware: string[]}
     * @throws HttpException 404 when no path matches, 405 when the path matches another method
     */
    public function match(string $method, string $path): array
    {
        $path = rtrim($path, '/') ?: '/';
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $params = array_map('urldecode', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware']];
        }

        if ($pathMatched) {
            throw new HttpException("Method {$method} not allowed for {$path}.", 405);
        }
        throw HttpException::notFound("API endpoint not found: {$path}");
    }

    /** All registered routes (used by tests and docs tooling). */
    public function routes(): array
    {
        return array_map(fn (array $r) => ['method' => $r['method'], 'path' => $r['path']], $this->routes);
    }
}
