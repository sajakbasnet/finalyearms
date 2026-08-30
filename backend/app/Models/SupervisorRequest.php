<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupervisorRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project asking a supervisor to take it on.
 *
 * Per project, not per student: a team asks once, as a team. Accepting writes
 * the per-student assignment rows so everything reading
 * `supervisor_assignments` keeps working.
 */
final class SupervisorRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'teacher_id',
        'requested_by',
        'status',
        'rationale',
        'response_note',
        'responded_at',
    ];

    protected $attributes = [
        'status' => SupervisorRequestStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => SupervisorRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'requested_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SupervisorRequestStatus::Pending);
    }
}
