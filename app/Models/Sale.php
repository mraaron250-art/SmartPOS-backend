<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Sale extends Model
{
    protected $table = 'sales';

    protected array $idColumns = ['user_id'];

    protected $hidden = ['updated_at'];

    protected $casts = [
        'subtotal' => 'float',
        'discount' => 'float',
        'total_amount' => 'float',
        'amount_received' => 'float',
        'change_amount' => 'float',
        'offline_synced' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Lookup by numeric ID or transaction number, with items loaded. */
    public static function findForDisplay(string $value): ?self
    {
        return self::findByIdOr($value, 'transaction_number')?->load('items');
    }
}
