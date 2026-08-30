<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobState;
use App\Enums\ProvisioningStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProvisioningJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'step',
        'state',
        'attempts',
        'last_error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => ProvisioningStep::class,
            'state' => JobState::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasSucceeded(): bool
    {
        return $this->state === JobState::Succeeded;
    }
}
