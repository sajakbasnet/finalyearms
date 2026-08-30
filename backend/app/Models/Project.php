<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'domain',
        'technology_stack',
        'category',
        'status',
        'supervisor_id',
        'academic_session_id',
        'student_group_id',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function proposalVersions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class);
    }

    public function supervisorRequests(): HasMany
    {
        return $this->hasMany(SupervisorRequest::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Every student on this project, whether it is individual or a team.
     *
     * @return list<int>
     */
    public function studentIds(): array
    {
        if ($this->student_group_id !== null) {
            return $this->group?->members()->pluck('student_id')->all() ?? [];
        }

        return $this->student_id !== null ? [(int) $this->student_id] : [];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }

    public function finalSubmission(): HasOne
    {
        return $this->hasOne(FinalSubmission::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function latestProposal(): HasOne
    {
        return $this->hasOne(ProposalVersion::class)->latestOfMany('version_number');
    }
}
