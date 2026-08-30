<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branding;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The minimum a freshly provisioned tenant database needs to be usable:
 * assignable roles, default templates, one Coordinator account, and a branding
 * row.
 *
 * Run by the provisioning pipeline against every new tenant. Deliberately
 * contains no demo data — that lives in DatabaseSeeder and must never reach a
 * customer database.
 *
 * Idempotent, so a retried provisioning step cannot duplicate rows or rotate an
 * existing admin's password.
 *
 * The generated password is written to stdout; the provisioner is responsible
 * for delivering it and forcing a change on first sign-in.
 */
final class TenantBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();

        $this->call(DefaultTemplatesSeeder::class);

        Branding::current()->fill([
            'institution_name' => (string) config('tenant.institution_name'),
        ])->save();

        $this->createInitialAdmin();
    }

    /**
     * Seeds the five assignable roles.
     *
     * platform_admin is excluded because it is a control-plane identity, and
     * team_lead because it is derived from student_group_members.is_leader —
     * UserRole::assignable() encodes both exclusions.
     */
    private function seedRoles(): void
    {
        foreach (UserRole::assignable() as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role->value],
                [
                    'name' => $role->label(),
                    'description' => $role->description(),
                ],
            );
        }
    }

    /**
     * Creates the institution's first account.
     *
     * Defaults to Coordinator: the Definition of Done is a Coordinator signing
     * in to a newly provisioned tenant, and it is the role that can set up
     * templates and teams. Override with TENANT_ADMIN_ROLE.
     */
    private function createInitialAdmin(): void
    {
        $email = (string) config('tenant.admin.email');

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Account {$email} already exists; leaving it untouched.");

            return;
        }

        $roleSlug = (string) config('tenant.admin.role');
        $role = UserRole::tryFrom($roleSlug);

        if ($role === null || $role->isPlatformScoped() || $role->isContextual()) {
            $this->command?->warn("'{$roleSlug}' is not an assignable role; falling back to coordinator.");
            $role = UserRole::Coordinator;
        }

        $password = Str::password(16);

        User::query()->create([
            'role_id' => Role::query()->where('slug', $role->value)->value('id'),
            'name' => (string) config('tenant.admin.name'),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->emitCredentials("{$role->label()} created: {$email} / {$password}");
    }

    /**
     * Write the generated credentials to stdout without console formatting.
     *
     * Symfony's output formatter treats `\<` and `\>` as escaped angle brackets
     * and strips the backslash on the way out. `Str::password()` can produce
     * both characters, so roughly one generated password in every few hundred
     * used to print one character shorter than the password that was hashed —
     * leaving the operator with a credential that could never log in, and no
     * clue beyond "invalid credentials".
     *
     * Raw output is the fix: nothing between the generator and the log gets to
     * rewrite the one value that has to be copied exactly.
     */
    protected function emitCredentials(string $line): void
    {
        $this->command?->getOutput()->writeln($line, OutputInterface::OUTPUT_RAW);
    }
}
