<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Sale;
use Tests\TestCase;

final class CheckoutTest extends TestCase
{
    private function stock(string $sku): int
    {
        return Product::query()->where('sku', $sku)->value('stock_quantity');
    }

    public function test_checkout_uses_db_prices_decrements_stock_and_writes_ledger_and_audit(): void
    {
        $before = $this->stock('BEV-002');

        $response = $this->call('POST', '/api/sales/checkout', [
            'items' => [['product_id' => '2', 'quantity' => 2, 'price' => 0.01], ['product_id' => 'SNK-001', 'quantity' => 1]],
            'payment_method' => 'Cash',
            'amount_received' => 300,
            'discount' => 5,
        ], $this->login('staff'));

        $this->assertStatus(201, $response);
        $sale = $response->data()['data'];
        $this->assertMatchesRegularExpression('/^TXN-\d{8}-\d{6}$/', $sale['transaction_number']);
        $this->assertSame(205.0, $sale['subtotal']);
        $this->assertSame(200.0, $sale['total_amount']);
        $this->assertSame(100.0, $sale['change_amount']);
        $this->assertSame('Juan Cashier', $sale['cashier_name']);
        $this->assertCount(2, $sale['items']);
        $this->assertSame($before - 2, $this->stock('BEV-002'));

        $this->assertSame(2, InventoryTransaction::query()->where('reference_id', $sale['transaction_number'])->where('transaction_type', 'SALE')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'POS_CHECKOUT')->where('target_id', $sale['transaction_number'])->exists());
    }

    public function test_repeated_transaction_number_is_idempotent(): void
    {
        $token = $this->login();
        $payload = ['items' => [['product_id' => '1', 'quantity' => 3]], 'amount_received' => 100, 'transaction_number' => 'TXN-OFFLINE-20261026-123456'];

        $first = $this->call('POST', '/api/sales/checkout', $payload, $token);
        $second = $this->call('POST', '/api/sales/checkout', $payload, $token);

        $this->assertStatus(201, $first);
        $this->assertStatus(201, $second);
        $this->assertSame($first->data()['data']['id'], $second->data()['data']['id']);
        $this->assertTrue($first->data()['data']['offline_synced']);
        $this->assertSame(1, Sale::query()->where('transaction_number', 'TXN-OFFLINE-20261026-123456')->count());
        $this->assertSame(42, $this->stock('BEV-001'));
    }

    public function test_failure_on_a_later_line_rolls_back_earlier_lines(): void
    {
        $response = $this->call('POST', '/api/sales/checkout', [
            'items' => [['product_id' => 'BEV-001', 'quantity' => 1], ['product_id' => 'GRO-002', 'quantity' => 999]],
            'amount_received' => 999999,
        ], $this->login());

        $this->assertStatus(400, $response);
        $this->assertStringContainsString('Insufficient stock for "Fresh Strawberry Jam 250g"', $response->data()['message']);
        $this->assertSame(45, $this->stock('BEV-001'));
        $this->assertSame(1, Sale::query()->count()); // only the seeded sale
    }

    public function test_cannot_oversell_last_units(): void
    {
        $token = $this->login();
        $codes = [];
        for ($i = 0; $i < 7; $i++) {
            $codes[] = $this->call('POST', '/api/sales/checkout', ['items' => [['product_id' => 'GRO-002', 'quantity' => 1]], 'amount_received' => 200], $token)->status();
        }

        $this->assertSame([201, 201, 201, 201, 201, 400, 400], $codes);
        $this->assertSame(0, $this->stock('GRO-002'));
    }

    public function test_validation_errors(): void
    {
        $token = $this->login();
        $cases = [
            [['items' => [], 'amount_received' => 1], 'Cart cannot be empty'],
            [['items' => [['product_id' => '1', 'quantity' => 1]]], 'Valid amount received is required.'],
            [['items' => [['product_id' => '1', 'quantity' => 0]], 'amount_received' => 1], 'Invalid item quantity'],
            [['items' => [['product_id' => '999', 'quantity' => 1]], 'amount_received' => 1], 'Product 999 not found.'],
            [['items' => [['product_id' => '1', 'quantity' => 1]], 'amount_received' => 1], 'Insufficient payment'],
            [['items' => [['product_id' => '1', 'quantity' => 1]], 'amount_received' => 100, 'discount' => 50], 'cannot exceed subtotal'],
            [['items' => [['product_id' => '1', 'quantity' => 1]], 'amount_received' => 100, 'discount' => -1], 'Discount cannot be negative.'],
        ];

        foreach ($cases as [$payload, $message]) {
            $response = $this->call('POST', '/api/sales/checkout', $payload, $token);
            $this->assertStatus(400, $response);
            $this->assertStringContainsString($message, $response->data()['message']);
        }

        Product::query()->where('sku', 'BEV-001')->update(['status' => 'INACTIVE']);
        $this->assertStringContainsString('is inactive', $this->call('POST', '/api/sales/checkout', ['items' => [['product_id' => '1', 'quantity' => 1]], 'amount_received' => 100], $token)->data()['message']);
    }

    public function test_cancel_restocks_once(): void
    {
        $token = $this->login();
        $tx = $this->call('POST', '/api/sales/checkout', ['items' => [['product_id' => 'BEV-001', 'quantity' => 5]], 'amount_received' => 100], $token)->data()['data']['transaction_number'];
        $this->assertSame(40, $this->stock('BEV-001'));

        $cancel = $this->call('POST', "/api/sales/{$tx}/cancel", ['reason' => 'Customer return'], $token);
        $this->assertStatus(200, $cancel);
        $this->assertSame('CANCELLED', $cancel->data()['data']['status']);
        $this->assertSame(45, $this->stock('BEV-001'));

        $this->assertStatus(400, $this->call('POST', "/api/sales/{$tx}/cancel", [], $token));
        $this->assertSame(45, $this->stock('BEV-001'));
        $this->assertStatus(403, $this->call('POST', "/api/sales/{$tx}/cancel", [], $this->login('staff')));
    }
}
