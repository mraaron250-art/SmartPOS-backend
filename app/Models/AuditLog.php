<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Append-only audit trail. Never update or delete rows.
 */
final class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public const UPDATED_AT = null;

    protected array $idColumns = ['user_id'];
}
