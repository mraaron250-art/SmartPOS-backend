<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_login_returns_token_user_and_http_only_cookie(): void
    {
        $response = $this->call('POST', '/api/auth/login', ['email' => 'OWNER@smartpos.local', 'password' => 'password123']);

        $this->assertStatus(200, $response);
        $body = $response->data();
        $this->assertTrue($body['success']);
        $this->assertSame('1', $body['user']['id']);
        $this->assertSame('OWNER', $body['user']['role']);
        $this->assertCount(39, $body['user']['permissions']);
        $this->assertSame($body['token'], $body['data']['token']);

        [$name, $value, $options] = $response->cookies()[0];
        $this->assertSame('smartpos_token', $name);
        $this->assertSame($body['token'], $value);
        $this->assertTrue($options['httponly']);
        $this->assertSame('Lax', $options['samesite']);
    }

    public function test_bad_credentials_and_missing_fields(): void
    {
        $this->assertStatus(400, $this->call('POST', '/api/auth/login', ['email' => 'owner@smartpos.local']));
        $this->assertStatus(401, $this->call('POST', '/api/auth/login', ['email' => 'nobody@x.y', 'password' => 'x']));
        $this->assertStatus(401, $this->call('POST', '/api/auth/login', ['email' => 'owner@smartpos.local', 'password' => 'wrong']));

        $this->assertTrue(AuditLog::query()->where('action', 'AUTH_LOGIN_FAILED')->where('result', 'FAILURE')->exists());
    }

    public function test_inactive_user_cannot_log_in_and_existing_token_stops_working(): void
    {
        $token = $this->login('staff');
        User::query()->where('email', 'staff@smartpos.local')->update(['status' => 'INACTIVE']);

        $this->assertStatus(403, $this->call('POST', '/api/auth/login', ['email' => 'staff@smartpos.local', 'password' => 'password123']));
        $this->assertStatus(401, $this->call('GET', '/api/auth/me', token: $token));
    }

    public function test_me_accepts_bearer_or_cookie_and_rejects_garbage(): void
    {
        $token = $this->login();

        $this->assertSame('owner@smartpos.local', $this->call('GET', '/api/auth/me', token: $token)->data()['user']['email']);
        $this->assertStatus(200, $this->call('GET', '/api/auth/me', cookies: ['smartpos_token' => $token]));
        $this->assertStatus(401, $this->call('GET', '/api/auth/me'));
        $this->assertStatus(401, $this->call('GET', '/api/auth/me', token: 'not-a-jwt'));
    }

    public function test_rbac_forbids_missing_permission_and_owner_bypasses(): void
    {
        $staff = $this->login('staff');
        $admin = $this->login('admin');

        $forbidden = $this->call('GET', '/api/users', token: $staff);
        $this->assertStatus(403, $forbidden);
        $this->assertSame('Forbidden: Missing required permission [users.view].', $forbidden->data()['message']);

        $this->assertStatus(200, $this->call('GET', '/api/products', token: $staff));
        $this->assertStatus(403, $this->call('POST', '/api/users/3/permissions', ['permission' => 'reports.view'], $admin));
        $this->assertStatus(200, $this->call('POST', '/api/users/3/permissions', ['permission' => 'reports.view'], $this->login()));
    }
}
