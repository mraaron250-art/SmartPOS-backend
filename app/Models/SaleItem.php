<?php

declare(strict_types=1);

namespace App\Models;

final class SaleItem extends Model
{
    protected $table = 'sale_items';

    public $timestamps = false;

    protected array $idColumns = ['product_id'];

    protected $hidden = ['id', 'sale_id'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];
}
