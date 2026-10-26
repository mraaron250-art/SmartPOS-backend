<?php

declare(strict_types=1);

namespace App\Models;

final class InventoryTransaction extends Model
{
    protected $table = 'inventory_transactions';

    public const UPDATED_AT = null;

    protected array $idColumns = ['product_id', 'user_id'];

    protected $casts = [
        'quantity' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
    ];
}
