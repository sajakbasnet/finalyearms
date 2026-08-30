<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed date within an academic session — proposal deadline, defence week,
 * results published.
 *
 * Activity templates anchor on day-offsets so they stay reusable across
 * sessions; these are the other half, the institutional dates a session
 * actually runs to.
 */
final class AcademicSessionDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'label',
        'description',
        'date',
        'is_deadline',
    ];

    protected $attributes = [
        'is_deadline' => false,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_deadline' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
