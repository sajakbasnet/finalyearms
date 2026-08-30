<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Enums\JobState;
use App\Enums\ProvisioningStep;
use App\Enums\TenantStatus;
use App\Models\ProvisioningJob;
use App\Models\Tenant;
use App\Models\TenantDatabase;
use App\Models\TenantDeployment;
use App\Models\TenantDomain;
use Illuminate\Support\Str;
use Throwable;

/**
 * Walks a tenant through the provisioning steps.
 *
 * Progress is written to `provisioning_jobs` rather than held in memory,
 * because this is expected to fail partway — a database server hiccups, an
 * image is missing, a container is slow to become healthy. Re-running resumes
 * from the first step that has not succeeded.
 */
final class ProvisioningPipeline
{
    public function __construct(
        private readonly TenantInfrastructure $infrastructure,
    ) {}

    /**
     * Registers a tenant if it is new, then runs the pipeline.
     *
     * Safe to call repeatedly for the same slug: registration is skipped when
     * the tenant exists, and completed steps are not repeated.
     */
    public function provision(string $slug, string $name, string $adminEmail): Tenant
    {
        $tenant = $this->register($slug, $name, $adminEmail);

        $this->run($tenant);

        return $tenant->refresh();
    }

    /**
     * Creates the registry rows without touching any infrastructure.
     *
     * Split out from provision() because the web UI needs the tenant to exist
     * immediately — so it can redirect to a page showing progress — while the
     * slow half runs on the queue. Returns the existing tenant if the slug is
     * already registered, so a retry does not duplicate it.
     */
    public function register(string $slug, string $name, string $adminEmail): Tenant
    {
        if (! Tenant::isValidSlug($slug)) {
            throw new \InvalidArgumentException(
                "Invalid slug '{$slug}': use 2-40 lowercase letters, digits and hyphens, starting with a letter and ending alphanumeric."
            );
        }

        return Tenant::query()->where('slug', $slug)->first()
            ?? $this->createRegistryRows($slug, $name, $adminEmail);
    }

    /** Runs every outstanding step, stopping at the first failure. */
    public function run(Tenant $tenant): void
    {
        foreach (ProvisioningStep::pipeline() as $step) {
            $this->runStep($tenant, $step);
        }

        $tenant->update(['status' => TenantStatus::Active]);
    }

    private function runStep(Tenant $tenant, ProvisioningStep $step): void
    {
        $job = ProvisioningJob::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'step' => $step,
        ]);

        if ($job->hasSucceeded()) {
            return;
        }

        $job->fill([
            'state' => JobState::Running,
            'attempts' => $job->attempts + 1,
            'started_at' => now(),
        ])->save();

        try {
            $this->execute($tenant, $step);
        } catch (Throwable $e) {
            $job->fill([
                'state' => JobState::Failed,
                'finished_at' => now(),
                'last_error' => Str::limit($e->getMessage(), 2000),
            ])->save();

            $tenant->update(['status' => TenantStatus::Failed]);

            throw new ProvisioningFailed($step, $e->getMessage(), $e);
        }

        $job->fill([
            'state' => JobState::Succeeded,
            'finished_at' => now(),
            'last_error' => null,
        ])->save();
    }

    private function execute(Tenant $tenant, ProvisioningStep $step): void
    {
        match ($step) {
            ProvisioningStep::CreateDatabase => $this->infrastructure->createDatabase($tenant),
            ProvisioningStep::CreateRole => $this->infrastructure->createRole($tenant),
            ProvisioningStep::RunMigrations => $this->infrastructure->runMigrations($tenant),
            ProvisioningStep::SeedBootstrap => $this->infrastructure->seedBootstrap($tenant),
            ProvisioningStep::StartContainer => $this->startContainer($tenant),
            ProvisioningStep::HealthCheck => $this->healthCheck($tenant),
        };
    }

    private function startContainer(Tenant $tenant): void
    {
        $this->infrastructure->startContainer($tenant);

        TenantDeployment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'service_name' => $tenant->serviceName(),
                'image_tag' => (string) config('provisioning.image_tag'),
                'last_deployed_at' => now(),
            ],
        );
    }

    private function healthCheck(Tenant $tenant): void
    {
        $healthy = $this->infrastructure->isHealthy($tenant);

        TenantDeployment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'service_name' => $tenant->serviceName(),
                'image_tag' => (string) config('provisioning.image_tag'),
                'health' => $healthy ? 'healthy' : 'unhealthy',
                'last_checked_at' => now(),
            ],
        );

        if (! $healthy) {
            throw new \RuntimeException("Tenant '{$tenant->slug}' did not become healthy.");
        }
    }

    /**
     * Creates the registry rows, including a fresh database password and a
     * per-tenant APP_KEY. Both are stored encrypted.
     */
    private function createRegistryRows(string $slug, string $name, string $adminEmail): Tenant
    {
        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => $name,
            'admin_email' => $adminEmail,
            'status' => TenantStatus::Provisioning,
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $slug.'.'.config('provisioning.base_domain'),
            'is_primary' => true,
        ]);

        // A narrow write credential for the shared title registry, issued once
        // and injected into the container. Not database access.
        $tenant->issueRegistryToken();

        TenantDatabase::query()->create([
            'tenant_id' => $tenant->id,
            'driver' => (string) config('provisioning.database.driver'),
            'host' => (string) config('provisioning.database.host'),
            'port' => (int) config('provisioning.database.port'),
            'database_name' => $tenant->databaseIdentifier(),
            'username' => $tenant->databaseIdentifier(),
            // Excludes characters that would need escaping in a DSN or a shell.
            'password' => Str::password(32, symbols: false),
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        return $tenant;
    }
}
