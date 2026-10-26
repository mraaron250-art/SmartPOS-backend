<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SystemTest extends TestCase
{
    public function test_health_reports_database_connected(): void
    {
        $response = $this->call('GET', '/api/health');

        $this->assertStatus(200, $response);
        $this->assertSame('healthy', $response->data()['status']);
        $this->assertSame('connected (PostgreSQL)', $response->data()['database']);
    }

    public function test_unknown_route_returns_json_404_and_wrong_method_405(): void
    {
        $this->assertStatus(404, $this->call('GET', '/api/nope'));
        $this->assertStatus(405, $this->call('DELETE', '/api/health'));
        $this->assertFalse($this->call('GET', '/api/nope')->data()['success']);
    }

    /** The router and docs/api/openapi.yaml must describe exactly the same operations. */
    public function test_routes_match_openapi_contract(): void
    {
        $yaml = file_get_contents(dirname(__DIR__, 2) . '/docs/api/openapi.yaml');
        preg_match_all('/^  (\/api\/\S+):\n((?:    .*\n|\n)+)/m', $yaml, $blocks, PREG_SET_ORDER);

        $contract = [];
        foreach ($blocks as [, $path, $body]) {
            preg_match_all('/^    (get|post|put|patch|delete):/m', $body, $methods);
            foreach ($methods[1] as $method) {
                $contract[] = strtoupper($method) . ' ' . $path;
            }
        }

        $routes = array_map(fn ($r) => "{$r['method']} {$r['path']}", $this->kernel()->router()->routes());
        $routes = array_values(array_filter($routes, fn ($r) => !str_contains($r, '/api/docs')));

        sort($contract);
        sort($routes);
        $this->assertCount(38, $contract);
        $this->assertSame($contract, $routes);
    }
}
