<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Throw from any layer to return `{ success: false, message }` with the given status.
 */
class HttpException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function badRequest(string $message): self
    {
        return new self($message, 400);
    }

    public static function unauthorized(string $message = 'Unauthorized: Access token is missing or invalid.'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
