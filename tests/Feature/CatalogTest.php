<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use Tests\TestCase;

final class CatalogTest extends TestCase
{
    public function test_product_lifecycle(): void
    {
        $token = $this->login('admin');

        $created = $this->call('POST', '/api/products', ['name' => 'Iced Tea', 'sku' => 'bev-009', 'category_id' => '1', 'selling_price' => 30, 'stock_quantity' => 4], $token);
        $this->assertStatus(201, $created);
        $product = $created->data()['data'];
        $this->assertSame('BEV-009', $product['sku']);
        $this->assertSame('Beverages', $product['category_name']);
        $this->assertIsString($product['id']);

        $this->assertStatus(409, $this->call('POST', '/api/products', ['name' => 'X', 'sku' => 'BEV-009', 'category_id' => '1', 'selling_price' => 1], $token));
        $this->assertStatus(400, $this->call('POST', '/api/products', ['name' => 'X', 'sku' => 'NEW-1', 'category_id' => '999', 'selling_price' => 1], $token));
        $this->assertStatus(400, $this->call('POST', '/api/products', ['name' => 'X'], $token));

        $this->assertSame('Iced Tea', $this->call('GET', '/api/products/BEV-009', token: $token)->data()['data']['name']);

        $updated = $this->call('PUT', "/api/products/{$product['id']}", ['stock_quantity' => 10, 'category_id' => '2'], $token);
        $this->assertSame('Snacks', $updated->data()['data']['category_name']);
        $this->assertSame(6, InventoryTransaction::query()->where('product_id', (int) $product['id'])->value('quantity'));

        $this->assertStatus(200, $this->call('DELETE', "/api/products/{$product['id']}", token: $token));
        $this->assertSame('INACTIVE', $this->call('GET', "/api/products/{$product['id']}", token: $token)->data()['data']['status']);
        $this->assertStatus(404, $this->call('GET', '/api/products/NOPE', token: $token));
    }

    public function test_product_list_filters_and_pagination(): void
    {
        $token = $this->login('staff');

        $page = $this->call('GET', '/api/products?limit=3&page=2', token: $token)->data();
        $this->assertCount(3, $page['data']);
        $this->assertSame(['page' => 2, 'limit' => 3, 'total_records' => 7, 'total_pages' => 3], $page['pagination']);

        $low = $this->call('GET', '/api/products?low_stock=true', token: $token)->data()['data'];
        $this->assertEqualsCanonicalizing(['SNK-002', 'GRO-002'], array_column($low, 'sku'));

        $this->assertSame(['BEV-002'], array_column($this->call('GET', '/api/products?search=coffee', token: $token)->data()['data'], 'sku'));
    }

    public function test_category_names_are_unique_ignoring_case_and_renames_sync_products(): void
    {
        $token = $this->login();

        $this->assertStatus(409, $this->call('POST', '/api/categories', ['name' => 'beverages'], $token));
        $this->assertStatus(200, $this->call('PUT', '/api/categories/1', ['name' => 'Drinks'], $token));
        $this->assertSame('Drinks', $this->call('GET', '/api/products/BEV-001', token: $token)->data()['data']['category_name']);
        $this->assertStatus(404, $this->call('GET', '/api/categories/abc', token: $token));
    }

    public function test_inventory_adjustments_are_atomic_and_never_negative(): void
    {
        $token = $this->login();

        $in = $this->call('POST', '/api/inventory/adjust', ['product_id' => 'GRO-002', 'transaction_type' => 'STOCK_IN', 'quantity' => 10, 'reason' => 'Delivery'], $token);
        $this->assertStatus(200, $in);
        $this->assertSame(15, $in->data()['data']['product']['new_stock']);

        $this->assertStatus(400, $this->call('POST', '/api/inventory/adjust', ['product_id' => 'GRO-002', 'transaction_type' => 'STOCK_OUT', 'quantity' => 16], $token));
        $this->assertStatus(400, $this->call('POST', '/api/inventory/adjust', ['product_id' => 'GRO-002', 'transaction_type' => 'SALE', 'quantity' => 1], $token));
        $this->assertStatus(400, $this->call('POST', '/api/inventory/adjust', ['product_id' => 'GRO-002', 'transaction_type' => 'STOCK_IN', 'quantity' => -1], $token));

        $history = $this->call('GET', '/api/inventory/history?product_id=7', token: $token)->data();
        $this->assertSame('STOCK_IN', $history['data'][0]['transaction_type']);

        $dash = $this->call('GET', '/api/inventory', token: $token)->data()['data'];
        $this->assertSame(7, $dash['total_products']);
    }
}
