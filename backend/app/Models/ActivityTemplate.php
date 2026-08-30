<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable plan of activities a coordinator can apply to projects.
 *
 * Published versions are immutable. Editing one forks a new draft at the next
 * version number rather than mutating in place, so a template cannot silently
 * rewrite the plan of a project that has already adopted it. `parent_id`
 * records where a version or clone came from.
 */
final class ActivityTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_type_id',
        'parent_id',
        'name',
        'description',
        'version',
        'status',
        'published_at',
        'is_default',
        'is_active',
        'is_sequential',
    ];

    protected $attributes = [
        'version' => 1,
        'status' => TemplateStatus::Draft->value,
        'is_default' => false,
        'is_active' => true,
        'is_sequential' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
            'published_at' => 'datetime',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_sequential' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActivityTemplateItem::class)->orderBy('sort_order');
    }

    /** The template this one was versioned or cloned from. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Versions and clones derived from this template. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', TemplateStatus::Published);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->published()->where('is_active', true);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isUsable(): bool
    {
        return $this->status->isUsable() && $this->is_active;
    }

    /**
     * Walks back to the original template, which identifies the version line.
     *
     * Bounded rather than recursive-until-null: a corrupt parent chain must not
     * be able to hang a request.
     */
    public function rootId(): int
    {
        $node = $this;

        for ($depth = 0; $depth < 50 && $node->parent_id !== null; $depth++) {
            $parent = $node->parent;

            if ($parent === null) {
                break;
            }

            $node = $parent;
        }

        return (int) $node->id;
    }

    /**
     * Latest day-offset any item reaches, accounting for a recurring log
     * running past its start. Null when no item carries an offset.
     */
    public function spanDays(): ?int
    {
        $ends = $this->items
            ->map(fn (ActivityTemplateItem $item): ?int => $item->endOffsetDays())
            ->filter(fn (?int $end): bool => $end !== null);

        return $ends->isEmpty() ? null : (int) $ends->max();
    }
}
