<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\JobState;
use App\Enums\ProvisioningStep;
use App\Enums\TenantStatus;
use App\Models\ProvisioningJob;
use App\Models\Tenant;
use App\Models\TenantDatabase;
use App\Provisioning\ProvisioningFailed;
use App\Provisioning\ProvisioningPipeline;
use App\Provisioning\TenantInfrastructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeTenantInfrastructure;
use Tests\TestCase;

final class ProvisioningPipelineTest extends TestCase
{
    use RefreshDatabase;

    private FakeTenantInfrastructure $infrastructure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->infrastructure = new FakeTenantInfrastructure;
        $this->app->instance(TenantInfrastructure::class, $this->infrastructure);
        config(['provisioning.base_domain' => 'supervisex.test']);
    }

    private function pipeline(): ProvisioningPipeline
    {
        return $this->app->make(ProvisioningPipeline::class);
    }

    public function test_it_provisions_a_tenant_end_to_end(): void
    {
        $tenant = $this->pipeline()->provision('tu', 'Tribhuvan University', 'coordinator@tu.edu.np');

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('tu.supervisex.test', $tenant->primaryDomain?->domain);
        $this->assertSame('tenant_tu', $tenant->database?->database_name);
        $this->assertSame('coordinator@tu.edu.np', $tenant->admin_email);
        $this->assertNotNull($tenant->uuid);
    }

    public function test_steps_run_in_order(): void
    {
        $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $this->assertSame([
            'create_database',
            'create_role',
            'run_migrations',
            'seed_bootstrap',
            'start_container',
            'health_check',
        ], $this->infrastructure->calls);
    }

    public function test_every_step_is_recorded_as_succeeded(): void
    {
        $tenant = $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $this->assertSame(6, $tenant->provisioningJobs()->count());
        $this->assertSame(
            0,
            $tenant->provisioningJobs()->where('state', '!=', JobState::Succeeded->value)->count(),
        );
    }

    /**
     * Credentials are the thing an attacker reaching the control plane is
     * after, so they must never be readable at rest.
     */
    public function test_credentials_are_stored_encrypted_and_are_unique_per_tenant(): void
    {
        $first = $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
        $second = $this->pipeline()->provision('ku', 'Kathmandu University', 'c@ku.edu.np');

        // Read through the query builder, not Eloquent: the model's `encrypted`
        // cast would decrypt it before we could inspect what is actually stored.
        $rawFirst = DB::table('tenant_databases')
            ->where('tenant_id', $first->id)
            ->value('password');

        $this->assertNotSame($first->database->password, $rawFirst);
        $this->assertStringNotContainsString($first->database->password, (string) $rawFirst);

        // A shared APP_KEY would make signed and encrypted payloads from one
        // institution valid at another.
        $this->assertNotSame($first->database->app_key, $second->database->app_key);
        $this->assertNotSame($first->database->password, $second->database->password);
    }

    public function test_credentials_are_hidden_from_serialisation(): void
    {
        $tenant = $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $array = $tenant->database->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('app_key', $array);
    }

    public function test_container_environment_carries_only_the_tenants_own_credentials(): void
    {
        $tenant = $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $environment = $tenant->database->containerEnvironment();

        $this->assertSame('tenant_tu', $environment['DB_DATABASE']);
        $this->assertStringStartsWith('base64:', $environment['APP_KEY']);

        // The load-bearing rule of the silo model: nothing here points at the
        // control plane.
        foreach ($environment as $value) {
            $this->assertStringNotContainsString('control_plane', $value);
        }
    }

    // ---------------------------------------------------------------
    // Failure and resume
    // ---------------------------------------------------------------

    public function test_a_failing_step_marks_the_tenant_failed_and_stops_the_pipeline(): void
    {
        $this->infrastructure->failOn = 'run_migrations';

        try {
            $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
            $this->fail('Expected ProvisioningFailed.');
        } catch (ProvisioningFailed $e) {
            $this->assertSame(ProvisioningStep::RunMigrations, $e->step);
        }

        $tenant = Tenant::query()->where('slug', 'tu')->firstOrFail();
        $this->assertSame(TenantStatus::Failed, $tenant->status);

        // Later steps must not have run.
        $this->assertNotContains('seed_bootstrap', $this->infrastructure->calls);
        $this->assertNotContains('start_container', $this->infrastructure->calls);

        $job = $tenant->provisioningJobs()
            ->where('step', ProvisioningStep::RunMigrations->value)
            ->firstOrFail();

        $this->assertSame(JobState::Failed, $job->state);
        $this->assertStringContainsString('simulated failure', (string) $job->last_error);
    }

    /**
     * The point of recording progress: a re-run resumes rather than redoing
     * work that already succeeded.
     */
    public function test_rerunning_after_a_failure_skips_completed_steps(): void
    {
        $this->infrastructure->failOn = 'run_migrations';
        $this->infrastructure->failTimes = 1;

        try {
            $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
        } catch (ProvisioningFailed) {
            // Expected.
        }

        $this->infrastructure->calls = [];

        $tenant = $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        // create_database and create_role already succeeded, so they are not
        // attempted again.
        $this->assertSame([
            'run_migrations',
            'seed_bootstrap',
            'start_container',
            'health_check',
        ], $this->infrastructure->calls);

        $this->assertSame(TenantStatus::Active, $tenant->status);
    }

    public function test_attempts_are_counted_across_reruns(): void
    {
        $this->infrastructure->failOn = 'run_migrations';
        $this->infrastructure->failTimes = 1;

        try {
            $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
        } catch (ProvisioningFailed) {
            // Expected.
        }

        $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $job = ProvisioningJob::query()
            ->where('step', ProvisioningStep::RunMigrations->value)
            ->firstOrFail();

        $this->assertSame(2, $job->attempts);
        $this->assertSame(JobState::Succeeded, $job->state);
        $this->assertNull($job->last_error);
    }

    public function test_re_provisioning_an_active_tenant_creates_no_duplicates(): void
    {
        $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
        $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');

        $this->assertSame(1, Tenant::query()->count());
        $this->assertSame(1, TenantDatabase::query()->count());
        $this->assertSame(6, ProvisioningJob::query()->count());
    }

    public function test_an_unhealthy_tenant_fails_the_pipeline(): void
    {
        $this->infrastructure->healthy = false;

        $this->expectException(ProvisioningFailed::class);

        $this->pipeline()->provision('tu', 'Tribhuvan University', 'c@tu.edu.np');
    }

    // ---------------------------------------------------------------
    // Slug validation — it reaches database and container identifiers
    // ---------------------------------------------------------------

    /**
     * @return list<array{0: string}>
     */
    public static function invalidSlugProvider(): array
    {
        return [
            ['TU'],                 // uppercase
            ['1tu'],                // leading digit
            ['t'],                  // too short
            ['tu_university'],      // underscore
            ['tu university'],      // space
            ['tu;DROP DATABASE'],   // injection attempt
            ['-tu'],                // leading hyphen
            ['tu-'],                // trailing hyphen
        ];
    }

    #[DataProvider('invalidSlugProvider')]
    public function test_invalid_slugs_are_rejected_before_touching_infrastructure(string $slug): void
    {
        try {
            $this->pipeline()->provision($slug, 'Somewhere', 'c@x.test');
            $this->fail("Expected '{$slug}' to be rejected.");
        } catch (\InvalidArgumentException) {
            // Nothing may have been attempted.
            $this->assertSame([], $this->infrastructure->calls);
            $this->assertSame(0, Tenant::query()->count());
        }
    }

    public function test_hyphenated_slugs_map_to_valid_database_identifiers(): void
    {
        $tenant = $this->pipeline()->provision('tu-pulchowk', 'IOE Pulchowk', 'c@ioe.edu.np');

        // Hyphens are legal in a hostname but not in a Postgres identifier.
        $this->assertSame('tu-pulchowk.supervisex.test', $tenant->primaryDomain?->domain);
        $this->assertSame('tenant_tu_pulchowk', $tenant->database?->database_name);
        $this->assertSame('tenant-tu-pulchowk', $tenant->serviceName());
    }
}
