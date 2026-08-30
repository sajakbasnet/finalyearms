<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProvisioningStep;
use App\Enums\TenantStatus;
use App\Http\Requests\StoreTenantRequest;
use App\Jobs\ProvisionTenantJob;
use App\Models\Tenant;
use App\Provisioning\ProvisioningPipeline;
use App\Services\ControlPlaneAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class TenantController extends Controller
{
    public function __construct(
        private readonly ProvisioningPipeline $pipeline,
        private readonly ControlPlaneAudit $audit,
    ) {}

    public function index(): View
    {
        $tenants = Tenant::query()
            ->with(['primaryDomain', 'deployment'])
            ->orderBy('slug')
            ->get();

        return view('tenants.index', [
            'tenants' => $tenants,
            'counts' => [
                'total' => $tenants->count(),
                'active' => $tenants->where('status', TenantStatus::Active)->count(),
                'provisioning' => $tenants->where('status', TenantStatus::Provisioning)->count(),
                'failed' => $tenants->where('status', TenantStatus::Failed)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->pipeline->register(
            $request->string('slug')->toString(),
            $request->string('name')->toString(),
            $request->string('admin_email')->toString(),
        );

        // Registration is instant; the pipeline is not. Queue it and send the
        // operator to a page that shows each step as it completes.
        ProvisionTenantJob::dispatch($tenant->id);

        $this->audit->record('tenant.provision_requested', $tenant, [
            'slug' => $tenant->slug,
            'admin_email' => $tenant->admin_email,
        ]);

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('status', "Provisioning {$tenant->name}. This page updates as each step completes.");
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['primaryDomain', 'database', 'deployment', 'provisioningJobs']);

        $jobs = $tenant->provisioningJobs->keyBy(fn ($job) => $job->step->value);

        return view('tenants.show', [
            'tenant' => $tenant,
            // Driven off the enum so the page shows every step, including ones
            // that have not started yet.
            'steps' => ProvisioningStep::pipeline(),
            'jobs' => $jobs,
        ]);
    }

    /**
     * Re-runs a failed pipeline. Every step is idempotent and completed ones
     * are skipped, so this resumes rather than starting over.
     */
    public function retry(Tenant $tenant): RedirectResponse
    {
        if ($tenant->status === TenantStatus::Active) {
            return redirect()
                ->route('tenants.show', $tenant)
                ->with('status', 'That institution is already active.');
        }

        $tenant->update(['status' => TenantStatus::Provisioning]);

        ProvisionTenantJob::dispatch($tenant->id);

        $this->audit->record('tenant.provision_retried', $tenant);

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('status', 'Retrying. Completed steps are skipped.');
    }
}
