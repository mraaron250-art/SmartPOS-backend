<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Writes rows to the append-only audit trail.
 */
final class AuditService
{
    /**
     * @param array{action: string, module: string, target_type?: string, target_id?: string|int|null,
     *              result?: string, details?: string} $entry
     */
    public static function log(?User $user, array $entry): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $entry['action'],
            'module' => $entry['module'],
            'target_type' => $entry['target_type'] ?? '',
            'target_id' => isset($entry['target_id']) ? (string) $entry['target_id'] : null,
            'result' => $entry['result'] ?? 'SUCCESS',
            'details' => $entry['details'] ?? '',
        ]);
    }
}
