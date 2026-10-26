<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The 39 RBAC permission keys and each role's default set.
 * Mirrors DEFAULT_ROLE_PERMISSIONS from the original lib/db/models.ts.
 */
final class Permissions
{
    public const ROLES = ['OWNER', 'ADMIN', 'STAFF'];

    public const ALL = [
        'pos.view', 'pos.create_sale', 'pos.apply_discount', 'pos.cancel_sale', 'pos.reprint_receipt',
        'products.view', 'products.create', 'products.edit', 'products.deactivate',
        'categories.view', 'categories.create', 'categories.edit', 'categories.deactivate',
        'inventory.view', 'inventory.adjust', 'inventory.stock_in', 'inventory.stock_out', 'inventory.view_history',
        'transactions.view', 'transactions.view_details', 'transactions.cancel', 'transactions.refund', 'transactions.reprint',
        'reports.view', 'reports.view_sales', 'reports.view_inventory', 'reports.export',
        'users.view', 'users.create', 'users.edit', 'users.deactivate', 'users.assign_role', 'users.assign_permission',
        'system.settings', 'system.configuration', 'system.audit_logs',
        'approvals.view', 'approvals.approve', 'approvals.reject',
    ];

    private const STAFF = [
        'pos.view', 'pos.create_sale', 'pos.apply_discount', 'pos.reprint_receipt',
        'products.view', 'categories.view', 'inventory.view', 'transactions.view', 'transactions.view_details',
    ];

    private const ADMIN_EXCLUDED = ['users.assign_permission', 'system.configuration'];

    /** @return string[] */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'OWNER' => self::ALL,
            'ADMIN' => array_values(array_diff(self::ALL, self::ADMIN_EXCLUDED)),
            'STAFF' => self::STAFF,
            default => [],
        };
    }

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::ALL, true);
    }

    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }
}
