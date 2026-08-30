<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityTemplate;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\TenantBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Definition of Done, end to end:
 *
 *   "a new tenant can be provisioned and a Coordinator can securely log in
 *    with role-based access."
 *
 * Exercises what a freshly provisioned tenant container does on first boot —
 * migrate, seed bootstrap — and then signs in over HTTP as the Coordinator that
 * seeding created, using only the generated password.
 */
final class TenantProvisioningDoDTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'coordinator@tu.edu.np';

    /**
     * Seeds a fresh tenant and returns the generated password, which is only
     * ever emitted to the provisioner's output — never stored in plaintext.
     */
    private function provisionTenant(): string
    {
        config([
            'tenant.institution_name' => 'Tribhuvan University',
            'tenant.admin.email' => self::EMAIL,
            'tenant.admin.name' => 'Programme Coordinator',
            'tenant.admin.role' => 'coordinator',
        ]);

        Artisan::call('db:seed', [
            '--class' => TenantBootstrapSeeder::class,
            '--force' => true,
        ]);

        preg_match('/Coordinator created: \S+ \/ (\S+)/', Artisan::output(), $matches);

        $this->assertArrayHasKey(1, $matches, 'The bootstrap seeder did not emit a password.');

        return $matches[1];
    }

    public function test_a_new_tenant_is_provisioned_with_everything_it_needs(): void
    {
        $this->provisionTenant();

        // Five assignable roles — platform_admin and team_lead excluded.
        $this->assertSame(5, Role::query()->count());
        $this->assertFalse(Role::query()->where('slug', 'platform_admin')->exists());
        $this->assertFalse(Role::query()->where('slug', 'team_lead')->exists());

        // Default project types and activity templates.
        $this->assertSame(4, ProjectType::query()->count());
        $this->assertSame(3, ActivityTemplate::query()->count());

        // Exactly one account: the institution's first Coordinator.
        $this->assertSame(1, User::query()->count());
        $this->assertSame(
            'coordinator',
            User::query()->where('email', self::EMAIL)->firstOrFail()->role?->slug,
        );
    }

    public function test_the_coordinator_can_log_in_with_the_generated_password(): void
    {
        $password = $this->provisionTenant();

        // The generated password is strong and never stored in plaintext.
        $this->assertGreaterThanOrEqual(16, strlen($password));
        $stored = User::query()->where('email', self::EMAIL)->value('password');
        $this->assertNotSame($password, $stored);
        $this->assertTrue(Hash::check($password, $stored));

        $response = $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => $password,
        ])
            ->assertOk()
            ->assertJsonPath('user.role.slug', 'coordinator')
            ->assertJsonPath('user.email', self::EMAIL);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_the_coordinator_token_grants_exactly_the_coordinator_surface(): void
    {
        $password = $this->provisionTenant();

        $token = $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => $password,
        ])->json('token');

        $headers = ['Authorization' => "Bearer {$token}"];

        // Coordinator's remit: templates, supervision overrides, oversight.
        $this->getJson('/api/coordinator/project-types', $headers)
            ->assertOk()
            ->assertJsonCount(4, 'data');
        $this->getJson('/api/coordinator/activity-templates', $headers)->assertOk();
        $this->getJson('/api/admin/proposals', $headers)->assertOk();
        $this->getJson('/api/dashboard', $headers)->assertOk();

        // Not the Institution Admin's: accounts, org structure, branding.
        $this->postJson('/api/admin/students', [], $headers)->assertForbidden();
        $this->postJson('/api/admin/departments', ['name' => 'X', 'code' => 'X'], $headers)
            ->assertForbidden();
        $this->postJson('/api/admin/branding', ['institution_name' => 'X'], $headers)
            ->assertForbidden();

        // Nor any other role's workspace.
        $this->getJson('/api/student/overview', $headers)->assertForbidden();
        $this->getJson('/api/teacher/proposals', $headers)->assertForbidden();
    }

    public function test_the_login_is_recorded_in_the_authentication_audit_trail(): void
    {
        $password = $this->provisionTenant();

        $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => $password,
        ])->assertOk();

        $this->assertDatabaseHas('auth_audit_logs', [
            'event' => 'login.succeeded',
            'email' => self::EMAIL,
            'succeeded' => true,
        ]);
    }

    public function test_the_tenant_serves_its_own_branding_before_sign_in(): void
    {
        $this->provisionTenant();

        // Unauthenticated: the login screen renders in the institution's
        // branding before any token exists.
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('data.institution_name', 'Tribhuvan University');
    }
}
