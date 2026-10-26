<?php

declare(strict_types=1);

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RequirePermission;

/*
 * Route middleware aliases used in routes/api.php.
 *   'auth'          -> request must carry a valid JWT
 *   'can:<perm>'    -> JWT + RBAC permission check
 */

return [
    'auth' => Authenticate::class,
    'can' => RequirePermission::class,
];
