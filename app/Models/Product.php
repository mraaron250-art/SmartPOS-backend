<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Product extends Model
{
    protected $table = 'products';

    protected array $idColumns = ['category_id'];

    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Lookup by numeric ID or SKU (the old API accepted both). */
    public static function findByIdOrSku(string $value): ?self
    {
        return self::findByIdOr($value, 'sku');
    }
}
