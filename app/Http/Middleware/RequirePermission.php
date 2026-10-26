<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Route middleware `can:<permission>`. Authenticates first, then checks RBAC.
 * OWNER passes every permission check.
 */
final class RequirePermission implements Middleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return (new Authenticate())->handle($request, function (Request $request) use ($next) {
            /** @var User $user */
            $user = $request->attribute('user');

            if ($user->role !== 'OWNER' && !in_array($this->permission, $user->effectivePermissions(), true)) {
                throw HttpException::forbidden("Forbidden: Missing required permission [{$this->permission}].");
            }
            return $next($request);
        });
    }
}
