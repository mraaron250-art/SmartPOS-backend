<?php

declare(strict_types=1);

namespace App\Models;

final class StoreSetting extends Model
{
    protected $table = 'store_settings';

    public const CREATED_AT = null;

    protected $casts = [
        'tax_rate_percent' => 'float',
    ];
}
