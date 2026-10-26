<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\CheckoutService;
use App\Support\Paginator;
use App\Support\Validate;

final class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Sale::query()->with('items')->orderByDesc('created_at')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($method = $request->query('payment_method')) {
            $query->where('payment_method', $method);
        }
        $userId = (string) $request->query('user_id', '');
        if ($userId !== '') {
            ctype_digit($userId) ? $query->where('user_id', (int) $userId) : $query->whereRaw('false');
        }
        if ($search = $request->query('search')) {
            $like = Validate::like($search);
            $query->where(fn ($q) => $q->where('transaction_number', 'ILIKE', $like)->orWhere('cashier_name', 'ILIKE', $like));
        }
        if ($from = Validate::date($request->query('date_from'))) {
            $query->where('created_at', '>=', $from->setTime(0, 0));
        }
        if ($to = Validate::date($request->query('date_to'))) {
            $query->where('created_at', '<', $to->setTime(0, 0)->modify('+1 day'));
        }

        return $this->paginated(Paginator::paginate($query, $request->queryInt('page', 1), $request->queryInt('limit', 20)));
    }

    public function show(Request $request): Response
    {
        return $this->ok($this->find($request)->toArray());
    }

    public function checkout(Request $request): Response
    {
        $items = $request->input('items');
        if (!is_array($items) || $items === [] || !array_is_list($items)) {
            throw HttpException::badRequest('Cart cannot be empty. Please select products to checkout.');
        }

        $amount = $request->input('amount_received');
        if (!is_numeric($amount)) {
            throw HttpException::badRequest('Valid amount received is required.');
        }

        $sale = (new CheckoutService())->checkout(
            $this->user($request),
            $items,
            (float) $amount,
            $request->input('discount', 0),
            (string) ($request->input('payment_method') ?: 'Cash'),
            $request->input('transaction_number') !== null ? (string) $request->input('transaction_number') : null,
        );

        return $this->created($sale->toArray(), 'Transaction completed successfully.');
    }

    public function cancel(Request $request): Response
    {
        $reason = Validate::text($request->input('reason') ?: 'Customer cancellation / return', 500);
        $sale = (new CheckoutService())->cancel($this->user($request), (string) $request->param('id'), $reason);

        return $this->ok($sale->toArray(), 'Transaction cancelled and inventory restored successfully.');
    }

    public function reprint(Request $request): Response
    {
        $sale = $this->find($request);

        AuditService::log($this->user($request), [
            'action' => 'RECEIPT_REPRINT',
            'module' => 'POS',
            'target_type' => 'SALE',
            'target_id' => $sale->transaction_number,
            'details' => "Reprinted receipt for sale {$sale->transaction_number}",
        ]);

        return $this->ok($sale->toArray(), 'Receipt reprinted successfully.');
    }

    private function find(Request $request): Sale
    {
        return Sale::findForDisplay((string) $request->param('id'))
            ?? throw HttpException::notFound('Transaction not found.');
    }
}
