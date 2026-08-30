<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProjectType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_employer',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $attributes = [
        'requires_employer' => false,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'requires_employer' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function activityTemplates(): HasMany
    {
        return $this->hasMany(ActivityTemplate::class);
    }
}
