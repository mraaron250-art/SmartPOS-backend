<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Base Eloquent model.
 * - IDs and foreign keys serialize as strings (API contract: `"id": "12"`).
 * - Dates serialize as ISO-8601 with offset.
 */
abstract class Model extends Eloquent
{
    protected $guarded = ['id'];

    /** Columns that hold other rows' IDs; serialized as strings like `id`. */
    protected array $idColumns = [];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        foreach (['id', ...$this->idColumns] as $column) {
            if (array_key_exists($column, $data) && $data[$column] !== null) {
                $data[$column] = (string) $data[$column];
            }
        }
        return $data;
    }

    /** Resolve "12" as a primary key, anything else via $fallbackColumn (e.g. SKU). */
    public static function findByIdOr(string $value, ?string $fallbackColumn = null): ?static
    {
        if (ctype_digit($value)) {
            return static::query()->find((int) $value);
        }
        return $fallbackColumn ? static::query()->where($fallbackColumn, $value)->first() : null;
    }
}
