<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Middleware\Cors;
use Throwable;

/**
 * HTTP kernel: Request -> global middleware -> route middleware -> controller -> Response.
 * Every error leaves as the standard `{ success: false, message }` JSON envelope.
 */
final class Kernel
{
    /** Middleware that wraps every request, outermost first. */
    private array $global = [
        Cors::class,
    ];

    /** Route middleware aliases. `name:arg` passes `arg` to the constructor. */
    private array $aliases = [];

    public function __construct(private readonly Router $router)
    {
    }

    public function alias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function handle(Request $request): Response
    {
        $core = function (Request $request): Response {
            $route = $this->router->match($request->method(), $request->path());
            $request->setParams($route['params']);

            $pipeline = array_map(fn (string $m) => $this->resolve($m), $route['middleware']);
            return $this->pipe($pipeline, fn (Request $r) => $this->invoke($route['handler'], $r))($request);
        };

        $global = array_map(fn (string $class) => new $class(), $this->global);

        try {
            return $this->pipe($global, fn (Request $r) => $this->guard($core, $r))($request);
        } catch (Throwable $e) {
            // Global middleware failed; still answer with JSON.
            return $this->renderException($e);
        }
    }

    private function guard(callable $core, Request $request): Response
    {
        try {
            return $core($request);
        } catch (Throwable $e) {
            return $this->renderException($e);
        }
    }

    private function pipe(array $middleware, callable $destination): callable
    {
        return array_reduce(
            array_reverse($middleware),
            fn (callable $next, Middleware $m) => fn (Request $r) => $m->handle($r, $next),
            $destination,
        );
    }

    private function resolve(string $definition): Middleware
    {
        [$name, $arg] = array_pad(explode(':', $definition, 2), 2, null);
        $class = $this->aliases[$name] ?? throw new \LogicException("Unknown middleware alias [{$name}].");
        return $arg === null ? new $class() : new $class($arg);
    }

    private function invoke(array|callable $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [new $class(), $method];
        }
        return $handler($request);
    }

    private function renderException(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], $e->status());
        }

        error_log(sprintf('[SmartPOS] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);

        return Response::json([
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'An unexpected server error occurred.',
        ], 500);
    }
}
