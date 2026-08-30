<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Tenant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'admin_email',
        'admin_name',
        'registry_token_hash',
        'registry_token_issued_at',
        'status',
        'plan',
        'suspended_reason',
    ];

    protected $attributes = [
        'status' => TenantStatus::Provisioning->value,
    ];

    /**
     * The primary key stays an auto-incrementing id; only `uuid` is generated.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getIncrementing(): bool
    {
        return true;
    }

    public function getKeyType(): string
    {
        return 'int';
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'registry_token_issued_at' => 'datetime',
        ];
    }

    /**
     * Issues a fresh registry token, returning the plaintext once.
     *
     * Only the hash is stored, so a leaked database dump yields no working
     * tokens. The plaintext is injected into the tenant's container at
     * provisioning and cannot be recovered afterwards — reissuing is the only
     * remedy, which also revokes the old one.
     */
    public function issueRegistryToken(): string
    {
        $plain = 'reg_'.bin2hex(random_bytes(24));

        $this->forceFill([
            'registry_token_hash' => hash('sha256', $plain),
            'registry_token_issued_at' => now(),
        ])->save();

        return $plain;
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class)->where('is_primary', true);
    }

    public function database(): HasOne
    {
        return $this->hasOne(TenantDatabase::class);
    }

    public function deployment(): HasOne
    {
        return $this->hasOne(TenantDeployment::class);
    }

    public function provisioningJobs(): HasMany
    {
        return $this->hasMany(ProvisioningJob::class);
    }

    /**
     * Slug is interpolated into database and container identifiers, so it is
     * constrained to a conservative shape before it ever gets there.
     */
    public static function isValidSlug(string $slug): bool
    {
        // 2-40 chars: starts with a letter, ends alphanumeric, hyphens allowed
        // between. Two-letter institution abbreviations ("tu", "ku") are common,
        // so the lower bound is 2.
        return preg_match('/^[a-z][a-z0-9-]{0,38}[a-z0-9]$/', $slug) === 1;
    }

    /** Hyphens are legal in a hostname but not in a Postgres identifier. */
    public function databaseIdentifier(): string
    {
        return 'tenant_'.str_replace('-', '_', $this->slug);
    }

    public function serviceName(): string
    {
        return 'tenant-'.$this->slug;
    }
}
