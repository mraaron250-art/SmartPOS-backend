<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish HTTP request wrapper built from PHP superglobals.
 * Controllers read input only through this object.
 */
final class Request
{
    /** Route parameters resolved by the Router, e.g. {id}. */
    private array $params = [];

    /** Per-request attributes set by middleware (e.g. the authenticated user). */
    private array $attributes = [];

    private ?array $json = null;

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $headers = [],
        private readonly array $cookies = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        // Apache strips Authorization unless forwarded via .htaccess
        if (!isset($headers['authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers,
            $_COOKIE,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** Query string value; empty strings are treated as missing (matches the old Next.js API). */
    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public function queryInt(string $key, int $default): int
    {
        $value = $this->query($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    /** Decoded JSON body. Invalid or empty JSON yields an empty array. */
    public function json(): array
    {
        if ($this->json === null) {
            $decoded = json_decode($this->rawBody, true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $this->json;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->json());
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
