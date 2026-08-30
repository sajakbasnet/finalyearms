<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The control plane is CLI-only, so nothing exercised this model until now —
 * which is how a reference to an uninstalled Sanctum trait survived unnoticed.
 * These are the smoke tests that stop that recurring.
 */
final class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_model_loads_and_persists(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Operator',
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]);

        $this->assertTrue($admin->exists);
        $this->assertTrue($admin->is_active);
        $this->assertDatabaseHas('platform_admins', ['email' => 'ops@supervisex.test']);
    }

    public function test_passwords_are_hashed_and_hidden(): void
    {
        $admin = PlatformAdmin::query()->create([
            'name' => 'Platform Operator',
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]);

        $this->assertNotSame('a-secure-password', $admin->password);
        $this->assertTrue(Hash::check('a-secure-password', $admin->password));
        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    /**
     * config/auth.php points the default provider at this model rather than the
     * stock App\Models\User, which does not exist in this app.
     */
    public function test_it_is_the_configured_auth_provider(): void
    {
        $this->assertSame(PlatformAdmin::class, config('auth.providers.users.model'));

        PlatformAdmin::query()->create([
            'name' => 'Platform Operator',
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]);

        $this->assertTrue(Auth::attempt([
            'email' => 'ops@supervisex.test',
            'password' => 'a-secure-password',
        ]));

        $this->assertFalse(Auth::attempt([
            'email' => 'ops@supervisex.test',
            'password' => 'wrong',
        ]));
    }
}
