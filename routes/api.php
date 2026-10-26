<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HealthController;

/*
 * SmartPOS REST API routes.
 * Contract: docs/api/openapi.yaml. Paths match the original Next.js API one-to-one.
 */

return function (Router $router): void {
    // System
    $router->get('/api/health', [HealthController::class, 'show']);
    $router->get('/api/docs', [DocsController::class, 'ui']);
    $router->get('/api/docs/openapi.yaml', [DocsController::class, 'spec']);
};
