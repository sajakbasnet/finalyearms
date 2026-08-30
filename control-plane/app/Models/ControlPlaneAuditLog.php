<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who did what in the control plane.
 *
 * Append-only. Answers "who provisioned, suspended or deleted this
 * institution, and when" — the questions that matter when an operator action
 * has to be explained after the fact.
 */
final class ControlPlaneAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_admin_id',
        'tenant_id',
        'action',
        'detail',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
