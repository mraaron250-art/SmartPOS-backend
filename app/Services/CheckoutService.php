<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Validate;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

/**
 * Atomic POS checkout and sale cancellation.
 *
 * Guarantees (see docs/ARCHITECTURE.md):
 *  - one DB transaction per checkout; any failure rolls back every line
 *  - conditional decrement `WHERE stock_quantity >= qty` (no overselling)
 *  - idempotent on transaction_number (UNIQUE), so offline replays are safe
 *  - prices always come from the database
 */
final class CheckoutService
{
    private const PG_UNIQUE_VIOLATION = '23505';

    /**
     * @param array<int, array{product_id?: mixed, quantity?: mixed}> $items
     */
    public function checkout(User $cashier, array $items, float $amountReceived, mixed $discount, string $paymentMethod, ?string $transactionNumber): Sale
    {
        if ($items === []) {
            throw HttpException::badRequest('Cart cannot be empty for checkout.');
        }

        $txNumber = $transactionNumber !== null && trim($transactionNumber) !== ''
            ? Validate::text($transactionNumber, 64)
            : $this->generateTransactionNumber();

        if ($existing = $this->findByTransactionNumber($txNumber)) {
            return $existing; // duplicate submission: return the original receipt
        }

        $discount = is_numeric($discount ?? 0) ? round((float) ($discount ?? 0), 2) : -1;
        if ($discount < 0) {
            throw HttpException::badRequest('Discount cannot be negative.');
        }
        if ($discount > 0 && $cashier->role !== 'OWNER' && !in_array('pos.apply_discount', $cashier->effectivePermissions(), true)) {
            throw HttpException::forbidden('Forbidden: Missing required permission [pos.apply_discount].');
        }

        $lines = $this->resolveLines($items);

        try {
            $saleId = DB::connection()->transaction(fn () => $this->commit(
                $cashier,
                $lines,
                $amountReceived,
                $discount,
                Validate::text($paymentMethod, 40) ?: 'Cash',
                $txNumber,
            ));
        } catch (QueryException $e) {
            // A parallel request with the same transaction_number won the race
            if ($e->getCode() === self::PG_UNIQUE_VIOLATION && ($existing = $this->findByTransactionNumber($txNumber))) {
                return $existing;
            }
            throw $e;
        }

        return Sale::query()->with('items')->findOrFail($saleId);
    }

    /**
     * Validate quantities and resolve products. Lines are merged per product and sorted by ID so
     * concurrent checkouts always lock rows in the same order (no deadlocks).
     *
     * @return array<int, array{product: Product, quantity: int}>
     */
    private function resolveLines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            $ref = (string) ($item['product_id'] ?? '');
            $rawQty = $item['quantity'] ?? null;
            $qty = Validate::positiveInt($rawQty, 'Invalid item quantity: ' . json_encode($rawQty));

            $product = $ref !== '' ? Product::findByIdOrSku($ref) : null;
            if ($product === null) {
                throw HttpException::badRequest("Product {$ref} not found.");
            }
            if ($product->status !== 'ACTIVE') {
                throw HttpException::badRequest("Product \"{$product->name}\" is inactive.");
            }

            $lines[$product->id] ??= ['product' => $product, 'quantity' => 0];
            $lines[$product->id]['quantity'] += $qty;
        }
        ksort($lines);
        return array_values($lines);
    }

    private function commit(User $cashier, array $lines, float $amountReceived, float $discount, string $paymentMethod, string $txNumber): int
    {
        $subtotalCents = 0;
        $decremented = [];

        foreach ($lines as ['product' => $product, 'quantity' => $qty]) {
            $row = DB::selectOne(
                "UPDATE products
                    SET stock_quantity = stock_quantity - ?, updated_at = NOW()
                  WHERE id = ? AND status = 'ACTIVE' AND stock_quantity >= ?
              RETURNING stock_quantity, selling_price, name, sku",
                [$qty, $product->id, $qty],
            );

            if ($row === null) {
                $available = (int) DB::table('products')->where('id', $product->id)->value('stock_quantity');
                throw HttpException::badRequest(
                    "Insufficient stock for \"{$product->name}\". Available: {$available}, requested: {$qty}."
                );
            }

            $unitCents = (int) round((float) $row->selling_price * 100);
            $subtotalCents += $unitCents * $qty;
            $decremented[] = [
                'product_id' => $product->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => $qty,
                'unit_cents' => $unitCents,
                'new_stock' => (int) $row->stock_quantity,
            ];
        }

        $subtotal = $subtotalCents / 100;
        if ($discount > $subtotal) {
            throw HttpException::badRequest(sprintf('Discount (₱%s) cannot exceed subtotal (₱%s).', $this->num($discount), $this->num($subtotal)));
        }

        $totalCents = $subtotalCents - (int) round($discount * 100);
        $receivedCents = (int) round($amountReceived * 100);
        if ($receivedCents < $totalCents) {
            throw HttpException::badRequest(sprintf(
                'Insufficient payment. Total: ₱%s, received: ₱%s.',
                number_format($totalCents / 100, 2, '.', ''),
                number_format($amountReceived, 2, '.', ''),
            ));
        }

        $sale = Sale::query()->create([
            'transaction_number' => $txNumber,
            'user_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total_amount' => $totalCents / 100,
            'payment_method' => $paymentMethod,
            'amount_received' => $receivedCents / 100,
            'change_amount' => ($receivedCents - $totalCents) / 100,
            'status' => 'COMPLETED',
            'offline_synced' => str_starts_with($txNumber, 'TXN-OFFLINE-'),
        ]);

        foreach ($decremented as $line) {
            $sale->items()->create([
                'product_id' => $line['product_id'],
                'product_name' => $line['name'],
                'sku' => $line['sku'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_cents'] / 100,
                'subtotal' => $line['unit_cents'] * $line['quantity'] / 100,
            ]);

            InventoryTransaction::query()->create([
                'product_id' => $line['product_id'],
                'product_name' => $line['name'],
                'user_id' => $cashier->id,
                'user_name' => $cashier->name,
                'transaction_type' => 'SALE',
                'quantity' => -$line['quantity'],
                'previous_stock' => $line['new_stock'] + $line['quantity'],
                'new_stock' => $line['new_stock'],
                'reference_id' => $txNumber,
                'reason' => 'POS Sale Checkout',
            ]);
        }

        AuditService::log($cashier, [
            'action' => 'POS_CHECKOUT',
            'module' => 'POS',
            'target_type' => 'SALE',
            'target_id' => $txNumber,
            'details' => sprintf(
                'Completed checkout %s: Total ₱%s, Paid ₱%s, Items: %d',
                $txNumber,
                number_format($totalCents / 100, 2, '.', ''),
                number_format($receivedCents / 100, 2, '.', ''),
                count($decremented),
            ),
        ]);

        return $sale->id;
    }

    public function cancel(User $user, string $saleRef, string $reason): Sale
    {
        $found = Sale::findByIdOr($saleRef, 'transaction_number');
        if ($found === null) {
            throw HttpException::badRequest('Sale not found.');
        }

        DB::connection()->transaction(function () use ($user, $found, $reason) {
            // Lock the sale so two cancellations cannot both restock
            $sale = Sale::query()->lockForUpdate()->with('items')->find($found->id);
            if ($sale->status === 'CANCELLED') {
                throw HttpException::badRequest('Sale is already cancelled.');
            }

            foreach ($sale->items as $item) {
                if ($item->product_id === null) {
                    continue; // product was deleted; nothing to restock
                }
                $row = DB::selectOne(
                    'UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ? RETURNING stock_quantity',
                    [$item->quantity, $item->product_id],
                );
                if ($row === null) {
                    continue;
                }
                InventoryTransaction::query()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'transaction_type' => 'STOCK_ADJUSTMENT',
                    'quantity' => $item->quantity,
                    'previous_stock' => (int) $row->stock_quantity - $item->quantity,
                    'new_stock' => (int) $row->stock_quantity,
                    'reference_id' => $sale->transaction_number,
                    'reason' => "Restock from Cancelled Sale {$sale->transaction_number}: {$reason}",
                ]);
            }

            $sale->update(['status' => 'CANCELLED']);

            AuditService::log($user, [
                'action' => 'SALE_CANCELLED',
                'module' => 'TRANSACTIONS',
                'target_type' => 'SALE',
                'target_id' => $sale->transaction_number,
                'details' => "Sale {$sale->transaction_number} cancelled. Reason: {$reason}",
            ]);
        });

        return Sale::query()->with('items')->findOrFail($found->id);
    }

    private function findByTransactionNumber(string $txNumber): ?Sale
    {
        return Sale::query()->with('items')->where('transaction_number', $txNumber)->first();
    }

    private function generateTransactionNumber(): string
    {
        do {
            $tx = sprintf('TXN-%s-%06d', date('Ymd'), random_int(100000, 999999));
        } while (Sale::query()->where('transaction_number', $tx)->exists());
        return $tx;
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
