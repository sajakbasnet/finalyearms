<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Tenant;
use App\Provisioning\TenantInfrastructure;

/**
 * Records what the pipeline asked for, and can be told to fail a given step.
 *
 * Lets the ordering, resume and failure paths be tested without a database
 * server or container runtime — those are the parts most likely to be wrong and
 * the hardest to exercise for real.
 */
final class FakeTenantInfrastructure implements TenantInfrastructure
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, int> */
    public array $callCounts = [];

    public ?string $failOn = null;

    public bool $healthy = true;

    /** Number of times $failOn should fail before succeeding. */
    public int $failTimes = PHP_INT_MAX;

    private function record(string $step): void
    {
        $this->calls[] = $step;
        $this->callCounts[$step] = ($this->callCounts[$step] ?? 0) + 1;

        if ($this->failOn === $step && $this->callCounts[$step] <= $this->failTimes) {
            throw new \RuntimeException("simulated failure in {$step}");
        }
    }

    public function createDatabase(Tenant $tenant): void
    {
        $this->record('create_database');
    }

    public function createRole(Tenant $tenant): void
    {
        $this->record('create_role');
    }

    public function runMigrations(Tenant $tenant): void
    {
        $this->record('run_migrations');
    }

    public function seedBootstrap(Tenant $tenant): void
    {
        $this->record('seed_bootstrap');
    }

    public function startContainer(Tenant $tenant): void
    {
        $this->record('start_container');
    }

    public function isHealthy(Tenant $tenant): bool
    {
        $this->record('health_check');

        return $this->healthy;
    }
}
