<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\JwtService;

/**
 * Requires a valid JWT (Bearer header first, then the smartpos_token cookie).
 * The user is re-loaded from the database so deactivation and permission changes apply immediately.
 * Sets the request attribute `user` (App\Models\User).
 */
final class Authenticate implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->attribute('user') === null) {
            $request->setAttribute('user', self::resolveUser($request) ?? throw HttpException::unauthorized());
        }
        return $next($request);
    }

    /** Active user for the request's token, or null. Never throws for bad tokens. */
    public static function resolveUser(Request $request): ?User
    {
        $token = self::token($request);
        if ($token === null) {
            return null;
        }

        $claims = (new JwtService())->verify($token);
        $id = $claims['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id)) {
            return null;
        }

        $user = User::query()->find((int) $id);
        return $user?->isActive() ? $user : null;
    }

    private static function token(Request $request): ?string
    {
        $header = $request->header('authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            if ($token !== '') {
                return $token;
            }
        }
        return $request->cookie(JwtService::COOKIE) ?: null;
    }
}
