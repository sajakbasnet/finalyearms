<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A platform operator.
 *
 * The only account type in the control plane. Distinct from every tenant role:
 * an Institution Admin inside a tenant cannot become one, because
 * `platform_admin` is never seeded into a tenant's roles table.
 *
 * No API tokens yet — the control plane is CLI-only, and Sanctum is not a
 * dependency of this app. Add `laravel/sanctum` and the `HasApiTokens` trait
 * when the Platform Admin API is built.
 */
final class PlatformAdmin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'platform_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Mirrors the column default so a new instance is active before saving. */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
