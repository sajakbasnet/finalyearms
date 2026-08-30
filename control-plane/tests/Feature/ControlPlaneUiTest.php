<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JobState;
use App\Enums\ProvisioningStep;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenantJob;
use App\Models\ControlPlaneAuditLog;
use App\Models\PlatformAdmin;
use App\Models\ProvisioningJob;
use App\Models\Tenant;
use App\Provisioning\ProvisioningPipeline;
use App\Provisioning\TenantInfrastructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeTenantInfrastructure;
use Tests\TestCase;

final class ControlPlaneUiTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['provisioning.base_domain' => 'supervisex.test']);
        $this->app->instance(TenantInfrastructure::class, new FakeTenantInfrastructure);

        $this->admin = PlatformAdmin::query()->create([
            'name' => 'Platform Operator',
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]);

        RateLimiter::clear('cp-login:ops@supervisex.test|127.0.0.1');
    }

    private function tenant(string $slug = 'tu'): Tenant
    {
        return $this->app->make(ProvisioningPipeline::class)
            ->register($slug, 'Tribhuvan University', 'coordinator@tu.edu.np');
    }

    // ---------------------------------------------------------------
    // Everything is behind auth
    // ---------------------------------------------------------------

    public function test_guests_are_redirected_to_login_from_every_page(): void
    {
        $tenant = $this->tenant();

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('tenants.index'))->assertRedirect(route('login'));
        $this->get(route('tenants.create'))->assertRedirect(route('login'));
        $this->get(route('tenants.show', $tenant))->assertRedirect(route('login'));
        $this->post(route('tenants.store'), [])->assertRedirect(route('login'));
        $this->post(route('tenants.retry', $tenant))->assertRedirect(route('login'));
    }

    public function test_an_operator_can_sign_in_and_out(): void
    {
        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ])->assertRedirect(route('tenants.index'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_operator_cannot_sign_in(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Deactivating an operator must end their live session, not wait for it to
     * expire on its own.
     */
    public function test_deactivation_ends_an_existing_session(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('tenants.index'))->assertOk();

        $this->admin->update(['is_active' => false]);

        $this->get(route('tenants.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_repeated_failures_are_locked_out(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('login'), [
                'email' => 'ops@supervisex.test',
                'password' => 'wrong',
            ]);
        }

        // Correct credentials are still refused while the lockout holds.
        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ---------------------------------------------------------------
    // Listing and detail
    // ---------------------------------------------------------------

    public function test_the_list_shows_tenants_and_counts(): void
    {
        $this->tenant('tu');
        $this->tenant('ku')->update(['status' => TenantStatus::Active]);

        $this->actingAs($this->admin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('Tribhuvan University')
            ->assertSee('tu.supervisex.test')
            ->assertSee('Institutions');
    }

    public function test_the_empty_state_invites_the_first_tenant(): void
    {
        $this->actingAs($this->admin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('No institutions yet');
    }

    public function test_the_detail_page_lists_every_pipeline_step(): void
    {
        $tenant = $this->tenant();

        $response = $this->actingAs($this->admin)
            ->get(route('tenants.show', $tenant))
            ->assertOk();

        foreach (ProvisioningStep::pipeline() as $step) {
            $response->assertSee($step->label());
        }
    }

    public function test_the_detail_page_shows_a_failed_step_and_its_error(): void
    {
        $tenant = $this->tenant();

        ProvisioningJob::query()->create([
            'tenant_id' => $tenant->id,
            'step' => ProvisioningStep::RunMigrations,
            'state' => JobState::Failed,
            'attempts' => 1,
            'last_error' => 'could not connect to server',
        ]);
        $tenant->update(['status' => TenantStatus::Failed]);

        $this->actingAs($this->admin)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('could not connect to server')
            ->assertSee('Retry provisioning');
    }

    /**
     * Database passwords and APP_KEYs are injected straight into a tenant's
     * container. There is no reason for them to reach a browser.
     */
    public function test_credentials_never_appear_in_the_ui(): void
    {
        $tenant = $this->tenant();
        $password = $tenant->database->password;
        $appKey = $tenant->database->app_key;

        $this->actingAs($this->admin)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertDontSee($password)
            ->assertDontSee($appKey);
    }

    // ---------------------------------------------------------------
    // Provisioning through the UI
    // ---------------------------------------------------------------

    public function test_creating_a_tenant_registers_it_and_queues_the_pipeline(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('tenants.store'), [
                'name' => 'Kathmandu University',
                'slug' => 'ku',
                'admin_email' => 'coordinator@ku.edu.np',
            ])
            ->assertRedirect(route('tenants.show', Tenant::query()->where('slug', 'ku')->firstOrFail()));

        $tenant = Tenant::query()->where('slug', 'ku')->firstOrFail();
        $this->assertSame(TenantStatus::Provisioning, $tenant->status);
        $this->assertSame('ku.supervisex.test', $tenant->primaryDomain?->domain);
        $this->assertNotNull($tenant->database);

        // The slow half must not run inside the request.
        Queue::assertPushed(ProvisionTenantJob::class);
    }

    public function test_the_slug_is_derived_from_the_name_when_omitted(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('tenants.store'), [
                'name' => 'Kathmandu University',
                'admin_email' => 'coordinator@ku.edu.np',
            ])->assertRedirect();

        $this->assertDatabaseHas('tenants', ['slug' => 'kathmandu-university']);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function invalidSlugProvider(): array
    {
        // 'TU' is absent deliberately — the form lowercases input rather than
        // scolding the operator for it. See the normalisation test below.
        return [['1tu'], ['t'], ['tu_uni'], ['tu uni'], ['-tu'], ['tu-']];
    }

    #[DataProvider('invalidSlugProvider')]
    public function test_invalid_slugs_are_rejected_by_the_form(string $slug): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('tenants.store'), [
                'name' => 'Somewhere',
                'slug' => $slug,
                'admin_email' => 'c@x.test',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(0, Tenant::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_slug_is_normalised_rather_than_rejected(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('tenants.store'), [
                'name' => 'Tribhuvan University',
                'slug' => '  TU  ',
                'admin_email' => 'c@tu.edu.np',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenants', ['slug' => 'tu']);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Queue::fake();
        $this->tenant('tu');

        $this->actingAs($this->admin)
            ->post(route('tenants.store'), [
                'name' => 'Another',
                'slug' => 'tu',
                'admin_email' => 'c@x.test',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_retrying_requeues_a_failed_tenant(): void
    {
        Queue::fake();

        $tenant = $this->tenant();
        $tenant->update(['status' => TenantStatus::Failed]);

        $this->actingAs($this->admin)
            ->post(route('tenants.retry', $tenant))
            ->assertRedirect(route('tenants.show', $tenant));

        $this->assertSame(TenantStatus::Provisioning, $tenant->fresh()->status);
        Queue::assertPushed(ProvisionTenantJob::class);
    }

    public function test_retrying_an_active_tenant_does_nothing(): void
    {
        Queue::fake();

        $tenant = $this->tenant();
        $tenant->update(['status' => TenantStatus::Active]);

        $this->actingAs($this->admin)
            ->post(route('tenants.retry', $tenant))
            ->assertRedirect(route('tenants.show', $tenant));

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_the_queued_job_runs_the_pipeline(): void
    {
        $tenant = $this->tenant();

        (new ProvisionTenantJob($tenant->id))->handle(
            $this->app->make(ProvisioningPipeline::class),
        );

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(6, $tenant->provisioningJobs()->count());
    }

    // ---------------------------------------------------------------
    // Audit
    // ---------------------------------------------------------------

    public function test_operator_actions_are_audited(): void
    {
        Queue::fake();

        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]);

        $this->post(route('tenants.store'), [
            'name' => 'Kathmandu University',
            'slug' => 'ku',
            'admin_email' => 'coordinator@ku.edu.np',
        ]);

        $this->assertDatabaseHas('control_plane_audit_logs', [
            'action' => 'login.succeeded',
            'platform_admin_id' => $this->admin->id,
        ]);

        $provision = ControlPlaneAuditLog::query()
            ->where('action', 'tenant.provision_requested')
            ->firstOrFail();

        $this->assertSame($this->admin->id, $provision->platform_admin_id);
        $this->assertSame('ku', $provision->detail['slug'] ?? null);
        $this->assertNotNull($provision->ip_address);
    }

    public function test_failed_logins_are_audited(): void
    {
        $this->post(route('login'), [
            'email' => 'ops@supervisex.test',
            'password' => 'wrong',
        ]);

        $this->assertDatabaseHas('control_plane_audit_logs', ['action' => 'login.failed']);
    }
}
