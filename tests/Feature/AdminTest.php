<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AdminTest extends TestCase
{
    public function test_users_never_expose_password_hash(): void
    {
        $token = $this->login();

        foreach ($this->call('GET', '/api/users', token: $token)->data()['data'] as $user) {
            $this->assertArrayNotHasKey('password_hash', $user);
        }

        $created = $this->call('POST', '/api/users', ['name' => 'Ana', 'email' => 'ana@smartpos.local', 'password' => 'longenough', 'role' => 'STAFF'], $token);
        $this->assertStatus(201, $created);
        $this->assertArrayNotHasKey('password_hash', $created->data()['data']);
        $this->assertCount(9, $created->data()['data']['permissions']);

        $this->assertStatus(200, $this->call('POST', '/api/auth/login', ['email' => 'ana@smartpos.local', 'password' => 'longenough']));
    }

    public function test_admin_cannot_escalate_to_owner(): void
    {
        $admin = $this->login('admin');

        $this->assertStatus(403, $this->call('POST', '/api/users', ['name' => 'X', 'email' => 'x@smartpos.local', 'password' => 'longenough', 'role' => 'OWNER'], $admin));
        $this->assertStatus(403, $this->call('PUT', '/api/users/1', ['name' => 'Hacked'], $admin));
        $this->assertStatus(403, $this->call('PUT', '/api/users/3', ['permissions' => ['system.configuration']], $admin));
        $this->assertStatus(400, $this->call('DELETE', '/api/users/2', token: $admin));
    }

    public function test_approvals_forbid_self_review_and_double_review(): void
    {
        $owner = $this->login();
        $staff = $this->login('staff');

        $own = $this->call('POST', '/api/approvals', ['action' => 'VOID', 'module' => 'POS'], $owner)->data()['data']['id'];
        $self = $this->call('POST', "/api/approvals/{$own}/approve", [], $owner);
        $this->assertStatus(400, $self);
        $this->assertStringContainsString('Self-approval is forbidden', $self->data()['message']);

        $this->assertStatus(403, $this->call('POST', '/api/approvals/1/approve', [], $staff));
        $this->assertSame('APPROVED', $this->call('POST', '/api/approvals/1/approve', ['comment' => 'OK'], $owner)->data()['data']['status']);
        $this->assertStatus(400, $this->call('POST', '/api/approvals/1/reject', [], $owner));
    }

    public function test_reports_and_audit_logs(): void
    {
        $owner = $this->login();
        $this->call('POST', '/api/sales/checkout', ['items' => [['product_id' => 'BEV-001', 'quantity' => 2]], 'amount_received' => 50], $owner);

        $dash = $this->call('GET', '/api/reports/dashboard', token: $owner)->data()['data'];
        $this->assertSame(2, $dash['today']['transaction_count']);
        $this->assertSame(245.0, $dash['today_sales']);
        $this->assertSame(122.5, $dash['avg_sale_value']);

        $sales = $this->call('GET', '/api/reports/sales?date_from=' . date('Y-m-d') . '&date_to=' . date('Y-m-d'), token: $owner)->data()['data'];
        $this->assertSame(2, $sales['summary']['total_transactions']);

        $rows = $this->call('GET', '/api/reports/product-sales', token: $owner)->data()['data'];
        $this->assertSame('BEV-002', $rows[0]['sku']);

        $logs = $this->call('GET', '/api/audit-logs?module=POS', token: $owner)->data();
        $this->assertSame('POS_CHECKOUT', $logs['data'][0]['action']);
        $this->assertStatus(403, $this->call('GET', '/api/audit-logs', token: $this->login('staff')));
    }
}
