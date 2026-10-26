<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object. Built by controllers, emitted once by the Kernel.
 */
final class Response
{
    private array $cookies = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = [$name, $value, $options];
        return $this;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    /** Decoded JSON body (used by tests). */
    public function data(): array
    {
        return json_decode($this->body, true) ?? [];
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        foreach ($this->cookies as [$name, $value, $options]) {
            setcookie($name, $value, $options);
        }
        echo $this->body;
    }
}
