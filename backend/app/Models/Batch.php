<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student intake cohort, scoped to a department.
 *
 * "2079 Intake" in Computer Science is a different cohort from the same-named
 * intake in Software Engineering, so the name is unique per department rather
 * than institution-wide. A batch outlives any single academic session and is
 * deliberately not tied to one.
 */
final class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'intake_year',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'intake_year' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
