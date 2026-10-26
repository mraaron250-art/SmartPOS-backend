<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\AuditService;
use App\Support\Paginator;
use App\Support\Validate;
use Illuminate\Database\Capsule\Manager as DB;

final class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::query()->orderBy('name')->orderBy('id');

        $categoryId = (string) $request->query('category_id', '');
        if ($categoryId !== '') {
            ctype_digit($categoryId) ? $query->where('category_id', (int) $categoryId) : $query->whereRaw('false');
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($request->query('low_stock') === 'true') {
            $query->whereColumn('stock_quantity', '<=', 'reorder_level');
        }
        if ($search = $request->query('search')) {
            $like = Validate::like($search);
            $query->where(fn ($q) => $q->where('name', 'ILIKE', $like)->orWhere('sku', 'ILIKE', $like)->orWhere('barcode', 'ILIKE', $like));
        }

        return $this->paginated(Paginator::paginate($query, $request->queryInt('page', 1), $request->queryInt('limit', 100)));
    }

    public function store(Request $request): Response
    {
        $name = Validate::text($request->input('name', ''), 200);
        $sku = strtoupper(Validate::text($request->input('sku', ''), 64));
        $categoryId = (string) $request->input('category_id', '');
        $sellingPrice = $request->input('selling_price');

        if ($name === '' || $sku === '' || $categoryId === '' || $sellingPrice === null || $sellingPrice === '') {
            throw HttpException::badRequest('Name, SKU, category, and selling price are required.');
        }

        $barcode = Validate::text($request->input('barcode', ''), 64) ?: null;
        $this->assertUnique($sku, $barcode);
        $category = $this->category($categoryId);

        $product = Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'barcode' => $barcode,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'cost_price' => Validate::money($request->input('cost_price', 0) ?: 0, 'cost_price'),
            'selling_price' => Validate::money($sellingPrice, 'selling_price'),
            'stock_quantity' => Validate::nonNegativeInt($request->input('stock_quantity', 0) ?: 0, 'stock_quantity'),
            'reorder_level' => Validate::nonNegativeInt($request->input('reorder_level', 10) ?: 10, 'reorder_level'),
            'description' => Validate::text($request->input('description', ''), 2000),
            'status' => 'ACTIVE',
        ])->refresh();

        AuditService::log($this->user($request), [
            'action' => 'PRODUCT_CREATE',
            'module' => 'PRODUCTS',
            'target_type' => 'PRODUCT',
            'target_id' => $product->id,
            'details' => "Created product \"{$product->name}\" (SKU: {$product->sku}, Stock: {$product->stock_quantity})",
        ]);

        return $this->created($product->toArray(), 'Product created successfully.');
    }

    public function show(Request $request): Response
    {
        return $this->ok($this->find($request)->toArray());
    }

    public function update(Request $request): Response
    {
        $product = $this->find($request);
        $user = $this->user($request);
        $in = $request->json();

        if (array_key_exists('name', $in)) {
            $product->name = Validate::text($in['name'], 200) ?: throw HttpException::badRequest('Product name cannot be empty.');
        }
        if (array_key_exists('sku', $in)) {
            $product->sku = strtoupper(Validate::text($in['sku'], 64)) ?: throw HttpException::badRequest('SKU cannot be empty.');
        }
        if (array_key_exists('barcode', $in)) {
            $product->barcode = Validate::text((string) $in['barcode'], 64) ?: null;
        }
        $this->assertUnique($product->isDirty('sku') ? $product->sku : null, $product->isDirty('barcode') ? $product->barcode : null, $product->id);

        if (!empty($in['category_id'])) {
            $category = $this->category((string) $in['category_id']);
            $product->category_id = $category->id;
            $product->category_name = $category->name;
        }
        foreach (['cost_price', 'selling_price'] as $field) {
            if (array_key_exists($field, $in)) {
                $product->{$field} = Validate::money($in[$field], $field);
            }
        }
        foreach (['stock_quantity', 'reorder_level'] as $field) {
            if (array_key_exists($field, $in)) {
                $product->{$field} = Validate::nonNegativeInt($in[$field], $field);
            }
        }
        if (array_key_exists('description', $in)) {
            $product->description = Validate::text((string) $in['description'], 2000);
        }
        if (array_key_exists('status', $in)) {
            $product->status = Validate::status($in['status']);
        }

        DB::connection()->transaction(function () use ($product, $user) {
            // Direct stock edits still leave a ledger trail
            if ($product->isDirty('stock_quantity')) {
                $previous = (int) $product->getOriginal('stock_quantity');
                InventoryTransaction::query()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'transaction_type' => 'STOCK_ADJUSTMENT',
                    'quantity' => $product->stock_quantity - $previous,
                    'previous_stock' => $previous,
                    'new_stock' => $product->stock_quantity,
                    'reason' => 'Stock edited from product form',
                ]);
            }
            $product->save();
        });

        AuditService::log($user, [
            'action' => 'PRODUCT_UPDATE',
            'module' => 'PRODUCTS',
            'target_type' => 'PRODUCT',
            'target_id' => $product->sku,
            'details' => "Updated product \"{$product->name}\" ({$product->sku})",
        ]);

        return $this->ok($product->toArray(), 'Product updated successfully.');
    }

    public function destroy(Request $request): Response
    {
        $product = $this->find($request);
        $product->update(['status' => 'INACTIVE']);

        AuditService::log($this->user($request), [
            'action' => 'PRODUCT_DEACTIVATE',
            'module' => 'PRODUCTS',
            'target_type' => 'PRODUCT',
            'target_id' => $product->sku,
            'details' => "Deactivated product \"{$product->name}\" ({$product->sku})",
        ]);

        return $this->ok(null, 'Product deactivated successfully.');
    }

    public function setStatus(Request $request): Response
    {
        $status = $request->input('status');
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw HttpException::badRequest("Status must be 'ACTIVE' or 'INACTIVE'.");
        }

        $product = $this->find($request);
        $product->update(['status' => $status]);

        AuditService::log($this->user($request), [
            'action' => 'PRODUCT_STATUS_CHANGE',
            'module' => 'PRODUCTS',
            'target_type' => 'PRODUCT',
            'target_id' => $product->sku,
            'details' => "Changed status of \"{$product->name}\" to {$status}",
        ]);

        return $this->ok($product->toArray(), "Product status changed to {$status}.");
    }

    private function find(Request $request): Product
    {
        return Product::findByIdOrSku((string) $request->param('id'))
            ?? throw HttpException::notFound('Product not found.');
    }

    private function category(string $id): Category
    {
        $category = ctype_digit($id) ? Category::query()->find((int) $id) : null;
        return $category ?? throw HttpException::badRequest('Selected category does not exist.');
    }

    private function assertUnique(?string $sku, ?string $barcode, ?int $exceptId = null): void
    {
        $others = fn () => Product::query()->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId));

        if ($sku !== null && $others()->where('sku', $sku)->exists()) {
            throw HttpException::conflict("Product with SKU \"{$sku}\" already exists.");
        }
        if ($barcode !== null && $others()->where('barcode', $barcode)->exists()) {
            throw HttpException::conflict("Product with barcode \"{$barcode}\" already exists.");
        }
    }
}
