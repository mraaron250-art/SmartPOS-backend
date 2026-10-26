<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'SmartPOS API',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'version' => '3.0.0',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila',
];
