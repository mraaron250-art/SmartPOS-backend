<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StoreSetting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Demo data matching the original SmartPOS in-memory seed.
 * All demo accounts use the password `password123`. Change them before production use.
 */
final class DatabaseSeeder
{
    public function run(bool $ifEmpty = false): string
    {
        if ($ifEmpty && User::query()->exists()) {
            return 'Database already has users; seeding skipped.';
        }

        DB::connection()->transaction(function () {
            $users = $this->users();
            $categories = $this->categories();
            $products = $this->products($categories);
            $this->sampleSale($users['owner'], $products);
            $this->extras($users);
        });

        return 'Seeded demo users, categories, products, sample sale, approval and settings.';
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $rows = [
            'owner' => ['Aaron Owner', 'owner@smartpos.local', 'OWNER'],
            'admin' => ['Store Manager', 'admin@smartpos.local', 'ADMIN'],
            'staff' => ['Juan Cashier', 'staff@smartpos.local', 'STAFF'],
        ];

        $users = [];
        foreach ($rows as $key => [$name, $email, $role]) {
            $users[$key] = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => 'password123',
                'role' => $role,
                'status' => 'ACTIVE',
                'permissions' => Permissions::forRole($role),
            ]);
        }
        return $users;
    }

    /** @return array<string, Category> */
    private function categories(): array
    {
        $rows = [
            'Beverages' => 'Drinks and refreshments',
            'Snacks' => 'Quick bites and chips',
            'Groceries' => 'Daily essentials',
            'Bakery' => 'Breads and pastries',
            'Personal Care' => 'Hygiene and care',
        ];

        $categories = [];
        foreach ($rows as $name => $description) {
            $categories[$name] = Category::query()->create(['name' => $name, 'description' => $description, 'status' => 'ACTIVE']);
        }
        return $categories;
    }

    /** @return array<string, Product> keyed by SKU */
    private function products(array $categories): array
    {
        // name, sku, barcode, category, cost, price, stock, reorder
        $rows = [
            ['Mineral Water 500ml', 'BEV-001', '480123456701', 'Beverages', 10, 20, 45, 15],
            ['Premium Brewed Coffee 300ml', 'BEV-002', '480123456702', 'Beverages', 35, 75, 32, 10],
            ['Artisan Chocolate Cookies', 'SNK-001', '480123456703', 'Snacks', 25, 55, 24, 10],
            ['Organic Granola Bar', 'SNK-002', '480123456704', 'Snacks', 30, 65, 8, 12],
            ['Whole Grain Sliced Bread', 'BAK-001', '480123456705', 'Bakery', 45, 85, 14, 8],
            ['Almond Milk 1L', 'GRO-001', '480123456706', 'Groceries', 80, 140, 19, 10],
            ['Fresh Strawberry Jam 250g', 'GRO-002', '480123456707', 'Groceries', 60, 110, 5, 8],
        ];

        $products = [];
        foreach ($rows as [$name, $sku, $barcode, $category, $cost, $price, $stock, $reorder]) {
            $products[$sku] = Product::query()->create([
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'category_id' => $categories[$category]->id,
                'category_name' => $category,
                'cost_price' => $cost,
                'selling_price' => $price,
                'stock_quantity' => $stock,
                'reorder_level' => $reorder,
                'status' => 'ACTIVE',
            ]);
        }
        return $products;
    }

    /** One completed sale so dashboards and transaction lists are not empty. */
    private function sampleSale(User $cashier, array $products): void
    {
        $lines = [['BEV-002', 2], ['SNK-001', 1]];
        $subtotal = 0.0;
        foreach ($lines as [$sku, $qty]) {
            $subtotal += $products[$sku]->selling_price * $qty;
        }

        $sale = Sale::query()->create([
            'transaction_number' => 'TXN-' . date('Ymd') . '-100001',
            'user_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total_amount' => $subtotal,
            'payment_method' => 'Cash',
            'amount_received' => 250,
            'change_amount' => 250 - $subtotal,
            'status' => 'COMPLETED',
        ]);

        foreach ($lines as [$sku, $qty]) {
            $product = $products[$sku];
            $sale->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $qty,
                'unit_price' => $product->selling_price,
                'subtotal' => $product->selling_price * $qty,
            ]);

            // Seeded stock is the post-sale level; record the matching ledger row
            InventoryTransaction::query()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'user_id' => $cashier->id,
                'user_name' => $cashier->name,
                'transaction_type' => 'SALE',
                'quantity' => -$qty,
                'previous_stock' => $product->stock_quantity + $qty,
                'new_stock' => $product->stock_quantity,
                'reference_id' => $sale->transaction_number,
                'reason' => 'POS Sale Checkout',
            ]);
        }
    }

    private function extras(array $users): void
    {
        ApprovalRequest::query()->create([
            'requested_by' => $users['staff']->id,
            'requester_name' => $users['staff']->name,
            'action' => 'VIP_DISCOUNT_30',
            'module' => 'POS',
            'target_type' => 'SALE',
            'target_id' => 'TXN-' . date('Ymd') . '-100002',
            'reason' => 'Senior citizen + loyalty bundle promotion request',
            'status' => 'PENDING',
        ]);

        AuditLog::query()->create([
            'user_id' => $users['owner']->id,
            'user_name' => $users['owner']->name,
            'action' => 'SYSTEM_INITIALIZE',
            'module' => 'AUTH',
            'result' => 'SUCCESS',
            'details' => 'SmartPOS database initialized on PostgreSQL',
        ]);

        StoreSetting::query()->create([
            'store_name' => 'SmartPOS Store',
            'address' => '123 Retail Avenue, Metro Manila',
            'phone' => '+63 912 345 6789',
            'currency_symbol' => '₱',
            'tax_rate_percent' => 12,
            'receipt_header' => 'SmartPOS Retail Store',
            'receipt_footer' => 'Thank you for shopping with us!',
        ]);
    }
}
