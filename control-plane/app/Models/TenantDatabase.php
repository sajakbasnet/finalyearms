<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's database credentials.
 *
 * `password` and `app_key` use the `encrypted` cast and are hidden from array
 * and JSON output — this row is what an attacker reaching the control plane
 * would be after.
 */
final class TenantDatabase extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'app_key',
        'rotated_at',
    ];

    protected $hidden = [
        'password',
        'app_key',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'app_key' => 'encrypted',
            'rotated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Environment injected into the tenant's container.
     *
     * This is the entire mechanism by which a tenant container learns who it
     * is. It receives its own credentials and nothing else — no control-plane
     * connection, so a compromised tenant cannot reach another's data.
     *
     * @return array<string, string>
     */
    public function containerEnvironment(): array
    {
        return [
            'APP_KEY' => (string) $this->app_key,
            'DB_CONNECTION' => (string) $this->driver,
            'DB_HOST' => (string) $this->host,
            'DB_PORT' => (string) $this->port,
            'DB_DATABASE' => (string) $this->database_name,
            'DB_USERNAME' => (string) $this->username,
            'DB_PASSWORD' => (string) $this->password,
        ];
    }
}
