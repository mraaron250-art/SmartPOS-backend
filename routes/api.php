<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;

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

    // Sales (register "checkout" before {id} so it is never read as an ID)
    $router->get('/api/sales', [SaleController::class, 'index'], ['can:transactions.view']);
    $router->post('/api/sales/checkout', [SaleController::class, 'checkout'], ['can:pos.create_sale']);
    $router->get('/api/sales/{id}', [SaleController::class, 'show'], ['can:transactions.view_details']);
    $router->post('/api/sales/{id}/cancel', [SaleController::class, 'cancel'], ['can:transactions.cancel']);
    $router->post('/api/sales/{id}/reprint', [SaleController::class, 'reprint'], ['can:pos.reprint_receipt']);

    // Reports
    $router->get('/api/reports/dashboard', [ReportController::class, 'dashboard'], ['can:reports.view']);
    $router->get('/api/reports/sales', [ReportController::class, 'sales'], ['can:reports.view_sales']);
    $router->get('/api/reports/product-sales', [ReportController::class, 'productSales'], ['can:reports.view_sales']);

    // Users & RBAC
    $router->get('/api/users', [UserController::class, 'index'], ['can:users.view']);
    $router->post('/api/users', [UserController::class, 'store'], ['can:users.create']);
    $router->get('/api/users/{id}', [UserController::class, 'show'], ['can:users.view']);
    $router->put('/api/users/{id}', [UserController::class, 'update'], ['can:users.edit']);
    $router->delete('/api/users/{id}', [UserController::class, 'destroy'], ['can:users.deactivate']);
    $router->get('/api/users/{id}/permissions', [UserController::class, 'permissions'], ['can:users.view']);
    $router->post('/api/users/{id}/permissions', [UserController::class, 'assignPermission'], ['can:users.assign_permission']);

    // Approvals
    $router->get('/api/approvals', [ApprovalController::class, 'index'], ['can:approvals.view']);
    $router->post('/api/approvals', [ApprovalController::class, 'store'], ['auth']);
    $router->post('/api/approvals/{id}/approve', [ApprovalController::class, 'approve'], ['can:approvals.approve']);
    $router->post('/api/approvals/{id}/reject', [ApprovalController::class, 'reject'], ['can:approvals.reject']);

    // Audit trail
    $router->get('/api/audit-logs', [AuditLogController::class, 'index'], ['can:system.audit_logs']);
};
