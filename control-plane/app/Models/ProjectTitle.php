<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved project title, contributed by a tenant.
 *
 * The registry exists so a student at one institution can discover that their
 * topic was already done at another — the one thing that genuinely has to
 * cross the tenant boundary.
 */
final class ProjectTitle extends Model
{
    use HasFactory;

    protected $table = 'project_titles';

    protected $fillable = [
        'tenant_id',
        'title',
        'normalised_title',
        'abstract',
        'academic_session',
        'year',
        'external_project_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'year' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Lower-cased, punctuation-stripped form used for lookups.
     *
     * Stored rather than computed at query time so a search is an index hit
     * instead of a scan that mangles every row.
     */
    public static function normalise(string $title): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(
            preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? '',
        )) ?? '');
    }
}
