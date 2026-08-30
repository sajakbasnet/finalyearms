<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'academic_session_id',
        'registration_number',
        'roll_number',
        'batch_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function supervisorAssignment(): HasOne
    {
        return $this->hasOne(SupervisorAssignment::class)->where('is_active', true);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(StudentGroupMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** The team they belong to, if any. */
    public function group(): ?StudentGroup
    {
        $membership = $this->groupMemberships()->with('group')->first();

        return $membership?->group;
    }

    public function leadsGroup(): bool
    {
        return $this->groupMemberships()->where('is_leader', true)->exists();
    }
}
