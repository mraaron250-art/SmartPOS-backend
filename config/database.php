<?php

declare(strict_types=1);

/*
 * PostgreSQL connection for Eloquent.
 * Render injects DATABASE_URL (postgres://user:pass@host:port/db). Discrete DB_* vars are a fallback.
 */

$url = $_ENV['DATABASE_URL'] ?? '';
$parts = $url !== '' ? parse_url($url) : [];
parse_str($parts['query'] ?? '', $query);

return [
    'driver' => 'pgsql',
    'host' => $parts['host'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
    'port' => (int) ($parts['port'] ?? ($_ENV['DB_PORT'] ?? 5432)),
    'database' => isset($parts['path']) ? ltrim($parts['path'], '/') : ($_ENV['DB_DATABASE'] ?? 'smartpos'),
    'username' => isset($parts['user']) ? urldecode($parts['user']) : ($_ENV['DB_USERNAME'] ?? 'smartpos'),
    'password' => isset($parts['pass']) ? urldecode($parts['pass']) : ($_ENV['DB_PASSWORD'] ?? ''),
    'charset' => 'utf8',
    'prefix' => '',
    'search_path' => 'public',
    'sslmode' => $query['sslmode'] ?? ($_ENV['DB_SSLMODE'] ?? 'prefer'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila',
];
