<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinalSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'thesis_path',
        'presentation_path',
        'poster_path',
        'source_code_path',
        'documentation_path',
        'demo_video_path',
        'github_repository',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    public function downloadableFiles(): array
    {
        return array_filter([
            'thesis' => $this->thesis_path,
            'presentation' => $this->presentation_path,
            'poster' => $this->poster_path,
            'source_code' => $this->source_code_path,
            'documentation' => $this->documentation_path,
            'demo_video' => $this->demo_video_path,
        ], fn ($path) => filled($path));
    }
}
