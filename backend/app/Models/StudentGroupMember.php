<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of a student in a group.
 *
 * `is_leader` is where the Team Lead capability comes from — it is per-group by
 * design, so a student can lead one team and be an ordinary member of another.
 */
final class StudentGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_group_id',
        'student_id',
        'is_leader',
    ];

    protected $attributes = [
        'is_leader' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
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
}
