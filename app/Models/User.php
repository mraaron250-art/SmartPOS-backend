<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Permissions;

final class User extends Model
{
    protected $table = 'users';

    protected $hidden = ['password_hash'];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function setPasswordAttribute(string $password): void
    {
        $this->attributes['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower(trim($email));
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /** Effective permissions: stored list, else the role default. */
    public function effectivePermissions(): array
    {
        return $this->permissions ?: Permissions::forRole($this->role);
    }

    /** Shape used by login and /auth/me. */
    public function toSession(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'permissions' => $this->effectivePermissions(),
        ];
    }
}
