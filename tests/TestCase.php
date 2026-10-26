<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Kernel;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use Database\Seeders\DatabaseSeeder;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Feature tests run real HTTP requests through the Kernel against PostgreSQL.
 * Each test starts from a fresh, seeded schema.
 */
abstract class TestCase extends BaseTestCase
{
    private static ?Kernel $kernel = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!str_ends_with(parse_url($_ENV['DATABASE_URL'], PHP_URL_PATH) ?: '', '_test')) {
            $this->fail('Refusing to run: DATABASE_URL must point at a *_test database.');
        }

        self::$kernel ??= require dirname(__DIR__) . '/bootstrap/app.php';
        (new Migrator(dirname(__DIR__) . '/database/migrations'))->fresh();
        (new DatabaseSeeder())->run();
    }

    protected function kernel(): Kernel
    {
        return self::$kernel;
    }

    protected function call(string $method, string $path, array $body = [], ?string $token = null, array $cookies = []): Response
    {
        [$path, $queryString] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($queryString, $query);

        $headers = ['content-type' => 'application/json'];
        if ($token !== null) {
            $headers['authorization'] = "Bearer {$token}";
        }

        return self::$kernel->handle(new Request($method, $path, $query, $headers, $cookies, $body ? json_encode($body) : ''));
    }

    protected function login(string $who = 'owner'): string
    {
        $response = $this->call('POST', '/api/auth/login', ['email' => "{$who}@smartpos.local", 'password' => 'password123']);
        $this->assertSame(200, $response->status(), $response->body());
        return $response->data()['token'];
    }

    protected function assertStatus(int $expected, Response $response): void
    {
        $this->assertSame($expected, $response->status(), $response->body());
    }
}
