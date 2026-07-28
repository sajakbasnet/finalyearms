<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'teacher_id',
        'innovation',
        'implementation',
        'documentation',
        'presentation',
        'testing',
        'overall_score',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
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
}
