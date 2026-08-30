<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student on a project.
 *
 * Distinct from `student_group_members`: the group is the team, this is who was
 * on the project when it ran. They usually match, but a team can change
 * membership while the project's record of who did the work should not.
 */
final class ProjectMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'student_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
