<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;

/*
 * SmartPOS REST API routes.
 * Contract: docs/api/openapi.yaml. Paths match the original Next.js API one-to-one.
 */

return function (Router $router): void {
    // System
    $router->get('/api/health', [HealthController::class, 'show']);
    $router->get('/api/docs', [DocsController::class, 'ui']);
    $router->get('/api/docs/openapi.yaml', [DocsController::class, 'spec']);

    // Auth
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout']);
    $router->get('/api/auth/me', [AuthController::class, 'me'], ['auth']);

    // Products
    $router->get('/api/products', [ProductController::class, 'index'], ['can:products.view']);
    $router->post('/api/products', [ProductController::class, 'store'], ['can:products.create']);
    $router->get('/api/products/{id}', [ProductController::class, 'show'], ['can:products.view']);
    $router->put('/api/products/{id}', [ProductController::class, 'update'], ['can:products.edit']);
    $router->delete('/api/products/{id}', [ProductController::class, 'destroy'], ['can:products.deactivate']);
    $router->patch('/api/products/{id}/status', [ProductController::class, 'setStatus'], ['can:products.deactivate']);

    // Categories
    $router->get('/api/categories', [CategoryController::class, 'index'], ['can:categories.view']);
    $router->post('/api/categories', [CategoryController::class, 'store'], ['can:categories.create']);
    $router->get('/api/categories/{id}', [CategoryController::class, 'show'], ['can:categories.view']);
    $router->put('/api/categories/{id}', [CategoryController::class, 'update'], ['can:categories.edit']);
    $router->delete('/api/categories/{id}', [CategoryController::class, 'destroy'], ['can:categories.deactivate']);

    // Inventory
    $router->get('/api/inventory', [InventoryController::class, 'dashboard'], ['can:inventory.view']);
    $router->post('/api/inventory/adjust', [InventoryController::class, 'adjust'], ['can:inventory.adjust']);
    $router->get('/api/inventory/history', [InventoryController::class, 'history'], ['can:inventory.view_history']);
};
