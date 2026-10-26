<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

/**
 * Read-only analytics over COMPLETED sales. Day boundaries use APP_TIMEZONE.
 */
final class ReportService
{
    public function dashboard(): array
    {
        $today = new DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $yesterday = $today->modify('-1 day');
        $monthStart = $today->modify('first day of this month');

        $todayTotals = $this->periodTotals($today, $tomorrow);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'COMPLETED')
            ->groupBy('sale_items.product_name')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->selectRaw('sale_items.product_name, SUM(sale_items.quantity) AS quantity_sold, SUM(sale_items.subtotal) AS revenue')
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'quantity_sold' => (int) $r->quantity_sold,
                'revenue' => round((float) $r->revenue, 2),
            ])->all();

        $stock = DB::table('products')->where('status', 'ACTIVE')->selectRaw('
            COUNT(*)                                                AS active_products,
            COUNT(*) FILTER (WHERE stock_quantity <= reorder_level) AS low_stock,
            COALESCE(SUM(stock_quantity), 0)                        AS total_units
        ')->first();

        return [
            'today' => $todayTotals,
            'yesterday' => $this->periodTotals($yesterday, $today),
            'this_month' => $this->periodTotals($monthStart, $tomorrow),
            'active_products_count' => (int) $stock->active_products,
            'low_stock_count' => (int) $stock->low_stock,
            'top_products' => $topProducts,
            // Flat fields read directly by the dashboard and reports views
            'today_sales' => $todayTotals['total_sales'],
            'today_transactions' => $todayTotals['transaction_count'],
            'total_units' => (int) $stock->total_units,
            'avg_sale_value' => $todayTotals['transaction_count'] > 0
                ? round($todayTotals['total_sales'] / $todayTotals['transaction_count'], 2)
                : 0.0,
        ];
    }

    public function sales(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $summary = $this->completed($from, $to)->selectRaw('
            COALESCE(SUM(subtotal), 0)     AS gross_sales,
            COALESCE(SUM(discount), 0)     AS total_discounts,
            COALESCE(SUM(total_amount), 0) AS net_sales,
            COUNT(*)                       AS total_transactions
        ')->first();

        $daily = $this->completed($from, $to)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') AS date, SUM(total_amount) AS total_sales, COUNT(*) AS transactions")
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM-DD')")
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->date,
                'total_sales' => round((float) $r->total_sales, 2),
                'transactions' => (int) $r->transactions,
            ])->all();

        return [
            'summary' => [
                'gross_sales' => round((float) $summary->gross_sales, 2),
                'total_discounts' => round((float) $summary->total_discounts, 2),
                'net_sales' => round((float) $summary->net_sales, 2),
                'total_transactions' => (int) $summary->total_transactions,
            ],
            'daily' => $daily,
        ];
    }

    public function productSales(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        return DB::table('sale_items')
            ->joinSub($this->completed($from, $to)->select('id'), 's', 's.id', '=', 'sale_items.sale_id')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->orderByDesc('total_revenue')
            ->selectRaw('sale_items.product_id, sale_items.product_name AS name, sale_items.sku,
                         SUM(sale_items.quantity) AS units_sold, SUM(sale_items.subtotal) AS total_revenue')
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id !== null ? (string) $r->product_id : null,
                'name' => $r->name,
                'sku' => $r->sku,
                'units_sold' => (int) $r->units_sold,
                'total_revenue' => round((float) $r->total_revenue, 2),
            ])->all();
    }

    /** @return array{total_sales: float, transaction_count: int} */
    private function periodTotals(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $row = DB::table('sales')
            ->where('status', 'COMPLETED')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total, COUNT(*) AS cnt')
            ->first();

        return ['total_sales' => round((float) $row->total, 2), 'transaction_count' => (int) $row->cnt];
    }

    /** COMPLETED sales within an inclusive date range (to = end of that day). */
    private function completed(?DateTimeImmutable $from, ?DateTimeImmutable $to): Builder
    {
        return DB::table('sales')
            ->where('status', 'COMPLETED')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->setTime(0, 0)))
            ->when($to, fn ($q) => $q->where('created_at', '<', $to->setTime(0, 0)->modify('+1 day')));
    }
}
