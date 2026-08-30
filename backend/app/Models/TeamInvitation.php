<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to join a team.
 *
 * Students join by accepting rather than being added, so membership is always
 * something they agreed to.
 */
final class TeamInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_group_id',
        'student_id',
        'invited_by',
        'status',
        'message',
        'expires_at',
        'responded_at',
    ];

    protected $attributes = [
        'status' => InvitationStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'invited_by');
    }

    /**
     * Still actionable: pending and not lapsed.
     *
     * Expiry is checked here rather than swept by a job, so an invitation that
     * has run out is inert the moment it is read.
     */
    public function isActionable(): bool
    {
        return $this->status->isOpen() && ! $this->hasExpired();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending);
    }
}
