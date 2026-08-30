<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'service_name',
        'image_tag',
        'schema_version',
        'health',
        'last_deployed_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_deployed_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
