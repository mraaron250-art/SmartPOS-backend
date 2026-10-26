<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A middleware receives the request and the next handler in the pipeline.
 * It may short-circuit by returning (or throwing) before calling $next.
 */
interface Middleware
{
    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next): Response;
}
