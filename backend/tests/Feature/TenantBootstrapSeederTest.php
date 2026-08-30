<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityTemplate;
use App\Models\Branding;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\TenantBootstrapSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class TenantBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_roles_branding_and_one_coordinator(): void
    {
        config([
            'tenant.institution_name' => 'Tribhuvan University',
            'tenant.admin.email' => 'coordinator@tu.edu.np',
            'tenant.admin.name' => 'Programme Coordinator',
            'tenant.admin.role' => 'coordinator',
        ]);

        $this->seed(TenantBootstrapSeeder::class);

        // Set, not sequence: the role data migration inserts coordinator and
        // employer before this seeder adds the rest, so id order is incidental.
        $this->assertEqualsCanonicalizing(
            ['institution_admin', 'coordinator', 'supervisor', 'student', 'employer'],
            Role::query()->pluck('slug')->all(),
        );

        $this->assertSame('Tribhuvan University', Branding::current()->institution_name);

        $coordinator = User::query()->where('email', 'coordinator@tu.edu.np')->firstOrFail();
        $this->assertSame('Programme Coordinator', $coordinator->name);
        $this->assertSame('coordinator', $coordinator->role?->slug);
        $this->assertSame(1, User::query()->count());
    }

    public function test_it_never_seeds_platform_admin_or_team_lead_roles(): void
    {
        $this->seed(TenantBootstrapSeeder::class);

        $slugs = Role::query()->pluck('slug')->all();

        // platform_admin in a tenant database would let an institution admin
        // grant themselves cross-tenant reach; team_lead is derived from
        // student_group_members.is_leader, not assigned.
        $this->assertNotContains('platform_admin', $slugs);
        $this->assertNotContains('team_lead', $slugs);
    }

    public function test_it_seeds_default_project_types_and_templates(): void
    {
        $this->seed(TenantBootstrapSeeder::class);

        $this->assertSame(4, ProjectType::query()->where('is_default', true)->count());
        $this->assertSame(3, ActivityTemplate::query()->where('is_default', true)->count());

        $development = ProjectType::query()->where('slug', 'development')->firstOrFail();
        $template = $development->activityTemplates()->firstOrFail();

        $this->assertSame(9, $template->items()->count());
        $this->assertSame('Proposal submission', $template->items()->first()?->title);

        // Defaults ship published so a new tenant has a usable template on day
        // one, rather than a draft someone has to find and publish.
        $this->assertTrue($template->isUsable());
    }

    public function test_it_falls_back_to_coordinator_for_an_unassignable_role(): void
    {
        // platform_admin must never be grantable from tenant configuration.
        config([
            'tenant.admin.email' => 'sneaky@tu.edu.np',
            'tenant.admin.role' => 'platform_admin',
        ]);

        $this->seed(TenantBootstrapSeeder::class);

        $user = User::query()->where('email', 'sneaky@tu.edu.np')->firstOrFail();
        $this->assertSame('coordinator', $user->role?->slug);
    }

    public function test_it_seeds_no_demo_data(): void
    {
        $this->seed(TenantBootstrapSeeder::class);

        // A customer database must never arrive pre-populated.
        $this->assertSame(0, Department::query()->count());
        $this->assertSame(0, Project::query()->count());
    }

    public function test_it_is_idempotent(): void
    {
        config(['tenant.admin.email' => 'coordinator@tu.edu.np']);

        $this->seed(TenantBootstrapSeeder::class);
        $originalPassword = User::query()->where('email', 'coordinator@tu.edu.np')->value('password');

        // Provisioning retries re-run this step; it must not duplicate rows or
        // silently rotate the account's password.
        $this->seed(TenantBootstrapSeeder::class);

        $this->assertSame(5, Role::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, Branding::query()->count());
        $this->assertSame(4, ProjectType::query()->count());
        $this->assertSame(3, ActivityTemplate::query()->count());
        $this->assertSame(
            $originalPassword,
            User::query()->where('email', 'coordinator@tu.edu.np')->value('password'),
        );
    }

    /**
     * Regression: the generated password must reach stdout byte for byte.
     *
     * Symfony's output formatter reads a backslash before an angle bracket as
     * an escape and drops the backslash on the way out. Str::password() emits
     * both characters, so every few hundred tenants the printed credential was
     * a character shorter than the one that had been hashed, and the operator
     * was left with a password that could not log in.
     *
     * Driven directly rather than through a full seed: reproducing it from a
     * real run means waiting for the right random draw.
     */
    public function test_the_credential_line_reaches_stdout_byte_for_byte(): void
    {
        $buffer = new BufferedOutput;
        $command = new Command;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        $seeder = new TenantBootstrapSeeder;
        $seeder->setCommand($command);

        // Every character the formatter would otherwise rewrite, in the
        // combinations Str::password() can actually produce.
        $password = 'aB3\\<xY\\>z<q>9!~';

        // The seeder is final, so reach the writer directly rather than
        // subclassing it.
        $emit = new ReflectionMethod($seeder, 'emitCredentials');
        $emit->invoke($seeder, "Coordinator created: coordinator@tu.edu.np / {$password}");

        preg_match('/created: \S+ \/ (\S+)/', $buffer->fetch(), $matches);

        $this->assertArrayHasKey(1, $matches, 'No credential line was written.');
        $this->assertSame($password, $matches[1]);
    }
}
