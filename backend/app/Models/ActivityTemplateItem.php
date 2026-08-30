<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityItemType;
use App\Enums\RecurringCadence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActivityTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_template_id',
        'type',
        'title',
        'description',
        'due_offset_days',
        'config',
        'blocks_progression',
        'sort_order',
    ];

    protected $attributes = [
        'type' => 'task',
        'sort_order' => 0,
        'blocks_progression' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityItemType::class,
            'config' => 'array',
            'blocks_progression' => 'boolean',
        ];
    }

    /** Cadence of a recurring log, or null for every other type. */
    public function cadence(): ?RecurringCadence
    {
        if ($this->type !== ActivityItemType::RecurringLog) {
            return null;
        }

        return RecurringCadence::tryFrom((string) ($this->config['cadence'] ?? ''));
    }

    public function occurrences(): int
    {
        return $this->type === ActivityItemType::RecurringLog
            ? (int) ($this->config['occurrences'] ?? 1)
            : 1;
    }

    /**
     * The day-offset by which this item is finished.
     *
     * A recurring log runs for its whole cadence span, so it ends later than it
     * starts — which matters when checking that a template fits its session.
     */
    public function endOffsetDays(): ?int
    {
        if ($this->due_offset_days === null) {
            return null;
        }

        $cadence = $this->cadence();

        return $cadence === null
            ? (int) $this->due_offset_days
            : (int) $this->due_offset_days + $cadence->spanDays($this->occurrences());
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ActivityTemplate::class, 'activity_template_id');
    }
}
