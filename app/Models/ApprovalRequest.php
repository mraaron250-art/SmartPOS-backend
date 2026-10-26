<?php

declare(strict_types=1);

namespace App\Models;

final class ApprovalRequest extends Model
{
    protected $table = 'approval_requests';

    public const UPDATED_AT = null;

    protected array $idColumns = ['requested_by', 'reviewed_by'];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];
}
