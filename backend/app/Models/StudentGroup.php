<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StudentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'supervisor_id',
        'academic_session_id',
        'is_individual',
    ];

    protected $attributes = [
        'is_individual' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_individual' => 'boolean',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(StudentGroupMember::class);
    }

    public function leader(): HasMany
    {
        return $this->members()->where('is_leader', true);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** Members who have actually joined, which is what size rules count. */
    public function memberCount(): int
    {
        return $this->members()->count();
    }

    /**
     * Members plus invitations still outstanding.
     *
     * Size limits are checked against this so a team cannot invite its way past
     * the maximum and only discover it as people accept.
     */
    public function committedCount(): int
    {
        return $this->memberCount() + $this->invitations()->pending()->count();
    }

    public function leaderStudentId(): ?int
    {
        return $this->members()->where('is_leader', true)->value('student_id');
    }

    public function hasMember(int $studentId): bool
    {
        return $this->members()->where('student_id', $studentId)->exists();
    }
}
