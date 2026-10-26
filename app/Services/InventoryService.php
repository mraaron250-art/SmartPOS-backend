<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Support\Validate;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Stock valuation and manual stock movements.
 */
final class InventoryService
{
    public const ADJUSTMENT_TYPES = ['STOCK_IN', 'STOCK_OUT', 'STOCK_ADJUSTMENT'];

    public function dashboard(): array
    {
        $row = DB::table('products')->where('status', 'ACTIVE')->selectRaw('
            COUNT(*)                                                    AS total_products,
            COALESCE(SUM(stock_quantity), 0)                            AS total_units,
            COALESCE(SUM(stock_quantity * cost_price), 0)               AS total_inventory_value,
            COALESCE(SUM(stock_quantity * selling_price), 0)            AS total_retail_value,
            COUNT(*) FILTER (WHERE stock_quantity <= reorder_level)     AS low_stock_count,
            COUNT(*) FILTER (WHERE stock_quantity <= 0)                 AS out_of_stock_count
        ')->first();

        return [
            'total_products' => (int) $row->total_products,
            'total_units' => (int) $row->total_units,
            'total_inventory_value' => round((float) $row->total_inventory_value, 2),
            'total_retail_value' => round((float) $row->total_retail_value, 2),
            'low_stock_count' => (int) $row->low_stock_count,
            'out_of_stock_count' => (int) $row->out_of_stock_count,
        ];
    }

    /**
     * Atomically apply a stock movement. STOCK_IN / STOCK_ADJUSTMENT add, STOCK_OUT subtracts.
     */
    public function adjust(User $user, string $productRef, string $type, mixed $quantity, string $reason = '', string $referenceId = ''): array
    {
        if (!in_array($type, self::ADJUSTMENT_TYPES, true)) {
            throw HttpException::badRequest('transaction_type must be one of: ' . implode(', ', self::ADJUSTMENT_TYPES) . '.');
        }
        $qty = Validate::positiveInt($quantity, 'Quantity must be a positive integer.');
        $delta = $type === 'STOCK_OUT' ? -$qty : $qty;

        return DB::connection()->transaction(function () use ($user, $productRef, $type, $delta, $reason, $referenceId) {
            $product = Product::findByIdOrSku($productRef);
            if ($product === null) {
                throw HttpException::badRequest('Product not found.');
            }

            // Row lock: concurrent adjustments/sales on this product wait for us
            $product = Product::query()->lockForUpdate()->find($product->id);
            $previous = $product->stock_quantity;
            $new = $previous + $delta;

            if ($new < 0) {
                throw HttpException::badRequest("Insufficient stock. Current: {$previous}, adjustment: {$delta}.");
            }

            $product->update(['stock_quantity' => $new]);

            $ledger = InventoryTransaction::query()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'transaction_type' => $type,
                'quantity' => $delta,
                'previous_stock' => $previous,
                'new_stock' => $new,
                'reference_id' => Validate::text($referenceId, 64),
                'reason' => Validate::text($reason, 1000) ?: "Manual {$type}",
            ])->refresh();

            $sign = $delta > 0 ? '+' : '';
            AuditService::log($user, [
                'action' => "INVENTORY_{$type}",
                'module' => 'INVENTORY',
                'target_type' => 'PRODUCT',
                'target_id' => $product->sku,
                'details' => "{$type} for \"{$product->name}\" ({$product->sku}): {$previous} -> {$new} ({$sign}{$delta})",
            ]);

            return [
                'product' => [
                    'id' => (string) $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'previous_stock' => $previous,
                    'new_stock' => $new,
                ],
                'transaction' => $ledger->toArray(),
            ];
        });
    }
}
