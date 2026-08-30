<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(AcademicSessionDate::class)->orderBy('date');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
