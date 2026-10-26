<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    protected $table = 'categories';

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public static function findByName(string $name): ?self
    {
        return self::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }
}
