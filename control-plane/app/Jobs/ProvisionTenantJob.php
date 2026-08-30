<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Provisioning\ProvisioningPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the provisioning pipeline off the request.
 *
 * Provisioning creates a database, runs migrations and polls a container until
 * it answers — up to a minute of work. Far too slow for an HTTP request, so the
 * web UI registers the tenant, dispatches this, and redirects to a page that
 * reads progress from `provisioning_jobs`.
 */
final class ProvisionTenantJob implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt. The pipeline records its own progress and every step is
     * idempotent, so recovery is an explicit retry by an operator — who can see
     * why it failed — rather than a blind requeue.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $tenantId,
    ) {}

    public function handle(ProvisioningPipeline $pipeline): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $pipeline->run($tenant);
    }

    /**
     * The pipeline already marks the tenant failed and records the error
     * against the step. This covers the case where the job dies before the
     * pipeline can — a timeout, or the worker being killed.
     */
    public function failed(?Throwable $e): void
    {
        Tenant::query()
            ->where('id', $this->tenantId)
            ->where('status', '!=', TenantStatus::Active->value)
            ->update(['status' => TenantStatus::Failed->value]);
    }
}
