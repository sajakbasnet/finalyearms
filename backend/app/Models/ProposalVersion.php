<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProposalVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'version_number',
        'title',
        'abstract',
        'background',
        'problem_statement',
        'objectives',
        'scope',
        'methodology',
        'literature_review',
        'timeline',
        'expected_outcome',
        'technologies',
        'references',
        'pdf_path',
        'status',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class);
    }
}
