<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthAuditLog extends Model
{
    /** Append-only: rows are written once and never updated. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event',
        'email',
        'succeeded',
        'reason',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuthEvent::class,
            'succeeded' => 'boolean',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
