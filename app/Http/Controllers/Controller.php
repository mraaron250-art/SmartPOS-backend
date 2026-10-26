<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Base controller: helpers for the `{ success, data, message }` envelope.
 */
abstract class Controller
{
    /** Authenticated user set by the Authenticate middleware. */
    protected function user(Request $request): User
    {
        return $request->attribute('user') ?? throw HttpException::unauthorized();
    }

    /** Route {param} that must be a numeric ID; anything else is a 404. */
    protected function idParam(Request $request, string $notFoundMessage, string $name = 'id'): int
    {
        $id = (string) $request->param($name);
        if (!ctype_digit($id)) {
            throw HttpException::notFound($notFoundMessage);
        }
        return (int) $id;
    }

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
