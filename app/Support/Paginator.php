<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Offset pagination in the API's `{ data, pagination }` shape.
 */
final class Paginator
{
    public const MAX_LIMIT = 500;

    public static function paginate(Builder $query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_LIMIT, max(1, $limit));

        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query->forPage($page, $limit)->get();

        return [
            'data' => $rows->toArray(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => $total,
                'total_pages' => max(1, (int) ceil($total / $limit)),
            ],
        ];
    }
}
