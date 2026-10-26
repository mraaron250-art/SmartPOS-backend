<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Support\Paginator;
use App\Support\Validate;

final class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        foreach (['module', 'action', 'result'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
        $userId = (string) $request->query('user_id', '');
        if ($userId !== '') {
            ctype_digit($userId) ? $query->where('user_id', (int) $userId) : $query->whereRaw('false');
        }
        if ($from = Validate::date($request->query('date_from'))) {
            $query->where('created_at', '>=', $from->setTime(0, 0));
        }
        if ($to = Validate::date($request->query('date_to'))) {
            $query->where('created_at', '<', $to->setTime(0, 0)->modify('+1 day'));
        }

        return $this->paginated(Paginator::paginate($query, $request->queryInt('page', 1), $request->queryInt('limit', 25)));
    }
}
