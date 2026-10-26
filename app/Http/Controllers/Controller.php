<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Response;

/**
 * Base controller: helpers for the `{ success, data, message }` envelope.
 */
abstract class Controller
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): Response
    {
        $body = ['success' => true];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if ($message !== null) {
            $body['message'] = $message;
        }
        return Response::json($body, $status);
    }

    protected function created(mixed $data, string $message): Response
    {
        return $this->ok($data, $message, 201);
    }

    /** Paginated list: `{ success, data: [...], pagination: {...} }` */
    protected function paginated(array $result): Response
    {
        return Response::json(['success' => true] + $result);
    }
}
