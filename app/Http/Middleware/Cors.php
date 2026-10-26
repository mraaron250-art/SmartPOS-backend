<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * CORS allowlist from CORS_ORIGINS (comma-separated). Answers preflight requests directly.
 * The Next.js frontend normally proxies /api server-side, so this only matters for direct browser calls.
 */
final class Cors implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $config = require dirname(__DIR__, 3) . '/config/cors.php';
        $origin = $request->header('origin');
        $allowed = $origin !== null && (in_array('*', $config['origins'], true) || in_array($origin, $config['origins'], true));

        $response = $request->method() === 'OPTIONS' ? Response::noContent() : $next($request);

        if ($allowed) {
            $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $config['methods']))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $config['headers']))
                ->withHeader('Access-Control-Max-Age', (string) $config['max_age'])
                ->withHeader('Vary', 'Origin');
        }

        return $response;
    }
}
