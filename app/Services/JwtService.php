<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/**
 * Issues and verifies the 24-hour HS256 access token.
 */
final class JwtService
{
    public const TTL_SECONDS = 86400;
    public const COOKIE = 'smartpos_token';
    private const ALGORITHM = 'HS256';

    private string $secret;

    public function __construct(?string $secret = null)
    {
        $secret ??= $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be set to at least 32 characters.');
        }
        $this->secret = $secret;
    }

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => (string) $user->id,
            'id' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'permissions' => $user->effectivePermissions(),
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ], $this->secret, self::ALGORITHM);
    }

    /** @return array<string, mixed>|null decoded claims, or null when invalid/expired */
    public function verify(string $token): ?array
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            return null;
        }
    }

    public static function cookieOptions(int $maxAge = self::TTL_SECONDS): array
    {
        return [
            'expires' => $maxAge > 0 ? time() + $maxAge : time() - 3600,
            'path' => '/',
            'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
