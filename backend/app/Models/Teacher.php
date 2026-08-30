<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_id',
        'designation',
        'max_projects',
    ];

    protected function casts(): array
    {
        return [
            'max_projects' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SupervisorAssignment::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'supervisor_id');
    }

    public function supervisorRequests(): HasMany
    {
        return $this->hasMany(SupervisorRequest::class);
    }

    /**
     * Projects this supervisor is currently carrying.
     *
     * Capacity is counted in projects, not students: a supervisor takes several
     * teams and a team is several students, so counting students would cap them
     * at a fraction of one team.
     *
     * Abandoned and completed work does not count against the load.
     */
    public function activeProjectCount(?int $excludingProjectId = null): int
    {
        return $this->projects()
            ->whereNotIn('status', [
                ProjectStatus::Completed->value,
                ProjectStatus::Rejected->value,
            ])
            ->when($excludingProjectId !== null, fn ($query) => $query->whereKeyNot($excludingProjectId))
            ->count();
    }

    /**
     * Whether one more project would fit.
     *
     * `$excludingProjectId` lets a project that is already theirs be re-checked
     * without counting itself.
     */
    public function hasCapacity(?int $excludingProjectId = null): bool
    {
        return $this->activeProjectCount($excludingProjectId) < $this->max_projects;
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->max_projects - $this->activeProjectCount());
    }

    /** Students across every project they supervise. */
    public function superviseeCount(): int
    {
        return $this->assignments()->where('is_active', true)->count();
    }
}
