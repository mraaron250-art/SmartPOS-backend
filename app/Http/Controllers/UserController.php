<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Permissions;
use App\Support\Validate;

final class UserController extends Controller
{
    private const MIN_PASSWORD = 8;

    public function index(Request $request): Response
    {
        $query = User::query()->orderBy('name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($search = $request->query('search')) {
            $like = Validate::like($search);
            $query->where(fn ($q) => $q->where('name', 'ILIKE', $like)->orWhere('email', 'ILIKE', $like));
        }

        return $this->ok($query->get()->toArray());
    }

    public function store(Request $request): Response
    {
        $actor = $this->user($request);
        $name = Validate::text($request->input('name', ''), 120);
        $email = strtolower(Validate::text($request->input('email', ''), 190));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', '');

        if ($name === '' || $email === '' || $password === '' || $role === '') {
            throw HttpException::badRequest('Name, email, password, and role are required.');
        }
        $this->assertEmail($email);
        $this->assertRole($actor, $role);
        $this->assertPassword($password);
        if (User::query()->where('email', $email)->exists()) {
            throw HttpException::badRequest('A user with this email address already exists.');
        }

        $permissions = $request->input('permissions');
        $permissions = is_array($permissions) && $permissions !== []
            ? $this->cleanPermissions($actor, $permissions)
            : Permissions::forRole($role);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'status' => 'ACTIVE',
            'permissions' => $permissions,
        ])->refresh();

        AuditService::log($actor, [
            'action' => 'USER_CREATE',
            'module' => 'USERS',
            'target_type' => 'USER',
            'target_id' => $user->id,
            'details' => "Created user account for \"{$user->name}\" ({$user->email}) with role {$user->role}",
        ]);

        return $this->created($user->toArray(), 'User created successfully.');
    }

    public function show(Request $request): Response
    {
        return $this->ok($this->find($request)->toArray());
    }

    public function update(Request $request): Response
    {
        $actor = $this->user($request);
        $user = $this->find($request);
        $in = $request->json();

        // Only an OWNER may modify an OWNER account
        if ($user->role === 'OWNER' && $actor->role !== 'OWNER') {
            throw HttpException::forbidden('Only an owner can modify an owner account.');
        }

        if (!empty($in['name'])) {
            $user->name = Validate::text($in['name'], 120);
        }
        if (!empty($in['email'])) {
            $email = strtolower(Validate::text($in['email'], 190));
            $this->assertEmail($email);
            if (User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                throw HttpException::conflict('A user with this email address already exists.');
            }
            $user->email = $email;
        }
        if (!empty($in['role']) && $in['role'] !== $user->role) {
            $this->requirePermission($actor, 'users.assign_role');
            $this->assertRole($actor, (string) $in['role']);
            $user->role = $in['role'];
            if (!isset($in['permissions'])) {
                $user->permissions = Permissions::forRole($user->role); // role change resets to defaults
            }
        }
        if (!empty($in['status'])) {
            $status = Validate::status($in['status']);
            if ($status === 'INACTIVE' && (int) $user->id === (int) $actor->id) {
                throw HttpException::badRequest('Cannot deactivate your own active user account.');
            }
            $user->status = $status;
        }
        if (isset($in['permissions']) && is_array($in['permissions'])) {
            $this->requirePermission($actor, 'users.assign_permission');
            $user->permissions = $this->cleanPermissions($actor, $in['permissions']);
        }
        if (!empty($in['password'])) {
            $this->assertPassword((string) $in['password']);
            $user->password = (string) $in['password'];
        }

        $user->save();

        AuditService::log($actor, [
            'action' => 'USER_UPDATE',
            'module' => 'USERS',
            'target_type' => 'USER',
            'target_id' => $user->id,
            'details' => "Updated user \"{$user->name}\" ({$user->email})",
        ]);

        return $this->ok($user->toArray(), 'User updated successfully.');
    }

    public function destroy(Request $request): Response
    {
        $actor = $this->user($request);
        $id = $this->idParam($request, 'User not found.');

        if ($id === (int) $actor->id) {
            throw HttpException::badRequest('Cannot deactivate your own active user account.');
        }

        $user = User::query()->find($id) ?? throw HttpException::notFound('User not found.');
        if ($user->role === 'OWNER' && $actor->role !== 'OWNER') {
            throw HttpException::forbidden('Only an owner can deactivate an owner account.');
        }
        $user->update(['status' => 'INACTIVE']);

        AuditService::log($actor, [
            'action' => 'USER_DEACTIVATE',
            'module' => 'USERS',
            'target_type' => 'USER',
            'target_id' => $user->id,
            'details' => "Deactivated user \"{$user->name}\"",
        ]);

        return $this->ok(null, 'User deactivated successfully.');
    }

    public function permissions(Request $request): Response
    {
        $user = $this->find($request);

        return $this->ok([
            'userId' => (string) $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'permissions' => $user->permissions ?? [],
        ]);
    }

    public function assignPermission(Request $request): Response
    {
        $actor = $this->user($request);
        $permission = (string) $request->input('permission', '');

        if ($permission === '') {
            throw HttpException::badRequest('Permission key is required.');
        }
        if (!Permissions::isValid($permission)) {
            throw HttpException::badRequest("Unknown permission key [{$permission}].");
        }

        $user = $this->find($request);
        $user->permissions = array_values(array_unique([...($user->permissions ?? []), ...$this->cleanPermissions($actor, [$permission])]));
        $user->save();

        AuditService::log($actor, [
            'action' => 'PERMISSION_ASSIGN',
            'module' => 'USERS',
            'target_type' => 'USER',
            'target_id' => $user->id,
            'details' => "Assigned permission \"{$permission}\" to user \"{$user->name}\"",
        ]);

        return $this->ok($user->permissions, "Permission {$permission} assigned successfully.");
    }

    private function find(Request $request): User
    {
        return User::query()->find($this->idParam($request, 'User not found.'))
            ?? throw HttpException::notFound('User not found.');
    }

    private function assertEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw HttpException::badRequest('A valid email address is required.');
        }
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD) {
            throw HttpException::badRequest('Password must be at least ' . self::MIN_PASSWORD . ' characters.');
        }
    }

    private function assertRole(User $actor, string $role): void
    {
        if (!Permissions::isValidRole($role)) {
            throw HttpException::badRequest('Role must be one of: ' . implode(', ', Permissions::ROLES) . '.');
        }
        if ($role === 'OWNER' && $actor->role !== 'OWNER') {
            throw HttpException::forbidden('Only an owner can grant the OWNER role.');
        }
    }

    private function requirePermission(User $actor, string $permission): void
    {
        if ($actor->role !== 'OWNER' && !in_array($permission, $actor->effectivePermissions(), true)) {
            throw HttpException::forbidden("Forbidden: Missing required permission [{$permission}].");
        }
    }

    /** Keep only known keys, and never let a non-owner grant permissions they don't hold. */
    private function cleanPermissions(User $actor, array $permissions): array
    {
        $clean = array_values(array_unique(array_filter($permissions, fn ($p) => is_string($p) && Permissions::isValid($p))));

        if ($actor->role !== 'OWNER') {
            $escalation = array_diff($clean, $actor->effectivePermissions());
            if ($escalation !== []) {
                throw HttpException::forbidden('Cannot grant permissions you do not hold: ' . implode(', ', $escalation) . '.');
            }
        }
        return $clean;
    }
}
