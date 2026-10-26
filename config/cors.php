<?php

declare(strict_types=1);

return [
    // e.g. "https://smartpos-frontend.vercel.app,http://localhost:3000"
    'origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', $_ENV['CORS_ORIGINS'] ?? 'http://localhost:3000,http://127.0.0.1:3000'),
    ))),
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Idempotency-Key'],
    'max_age' => 86400,
];
