<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\HttpException;

/**
 * Small input validators. Each returns the clean value or throws a 400.
 */
final class Validate
{
    public static function money(mixed $value, string $field): float
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw HttpException::badRequest("{$field} must be a number greater than or equal to 0.");
        }
        return round((float) $value, 2);
    }

    public static function nonNegativeInt(mixed $value, string $field): int
    {
        if (!is_numeric($value) || (float) $value < 0 || floor((float) $value) != (float) $value) {
            throw HttpException::badRequest("{$field} must be a whole number greater than or equal to 0.");
        }
        return (int) $value;
    }

    public static function positiveInt(mixed $value, string $message): int
    {
        if (!is_numeric($value) || (float) $value <= 0 || floor((float) $value) != (float) $value) {
            throw HttpException::badRequest($message);
        }
        return (int) $value;
    }

    public static function status(mixed $value): string
    {
        if (!in_array($value, ['ACTIVE', 'INACTIVE'], true)) {
            throw HttpException::badRequest("Status must be 'ACTIVE' or 'INACTIVE'.");
        }
        return $value;
    }

    public static function text(mixed $value, int $max = 255): string
    {
        $text = trim((string) $value);
        return mb_substr($text, 0, $max);
    }

    /** Escape LIKE wildcards in user search input. */
    public static function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    /** Parse YYYY-MM-DD (or any strtotime date); returns null for empty/invalid. */
    public static function date(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw HttpException::badRequest("Invalid date: {$value}");
        }
    }
}
