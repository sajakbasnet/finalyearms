<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Models\Tenant;

/**
 * The side-effecting half of provisioning.
 *
 * Behind an interface so the pipeline's ordering, retry, and failure handling
 * can be tested without a database server or a container runtime — the parts
 * most likely to be wrong are the ones hardest to exercise for real.
 *
 * Implementations must be idempotent: every method may be called again after a
 * failure partway through.
 */
interface TenantInfrastructure
{
    public function createDatabase(Tenant $tenant): void;

    /** Creates the login role and grants it its own database only. */
    public function createRole(Tenant $tenant): void;

    public function runMigrations(Tenant $tenant): void;

    public function seedBootstrap(Tenant $tenant): void;

    public function startContainer(Tenant $tenant): void;

    public function isHealthy(Tenant $tenant): bool;
}
