<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\InventoryTransaction;
use App\Services\InventoryService;
use App\Support\Paginator;

final class InventoryController extends Controller
{
    public function dashboard(Request $request): Response
    {
        return $this->ok((new InventoryService())->dashboard());
    }

    public function adjust(Request $request): Response
    {
        $productId = (string) $request->input('product_id', '');
        $type = (string) $request->input('transaction_type', '');

        if ($productId === '' || $type === '' || !$request->has('quantity')) {
            throw HttpException::badRequest('product_id, transaction_type, and quantity are required.');
        }

        $result = (new InventoryService())->adjust(
            $this->user($request),
            $productId,
            $type,
            $request->input('quantity'),
            (string) $request->input('reason', ''),
            (string) $request->input('reference_id', ''),
        );

        return $this->ok($result, 'Inventory stock adjusted successfully.');
    }

    public function history(Request $request): Response
    {
        $query = InventoryTransaction::query()->orderByDesc('created_at')->orderByDesc('id');

        $productId = (string) $request->query('product_id', '');
        if ($productId !== '') {
            ctype_digit($productId) ? $query->where('product_id', (int) $productId) : $query->whereRaw('false');
        }
        if ($type = $request->query('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        return $this->paginated(Paginator::paginate($query, $request->queryInt('page', 1), $request->queryInt('limit', 20)));
    }
}
