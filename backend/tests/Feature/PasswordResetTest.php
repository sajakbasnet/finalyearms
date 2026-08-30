<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_reset_link_is_sent_for_a_known_address(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'coordinator@fyp.local',
        ])->assertOk();

        $user = User::query()->where('email', 'coordinator@fyp.local')->firstOrFail();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * The response must not distinguish a registered address from an unknown
     * one, or it becomes an account-enumeration oracle.
     */
    public function test_an_unknown_address_gets_the_same_response(): void
    {
        Notification::fake();

        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'coordinator@fyp.local']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@fyp.local']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));

        Notification::assertCount(1);
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        Notification::fake();

        $user = User::query()->where('email', 'coordinator@fyp.local')->firstOrFail();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-secure-password-1', $user->fresh()->password));

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-secure-password-1',
        ])->assertOk();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'coordinator@fyp.local',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ])->assertUnprocessable();

        // Old credentials must still work after a failed reset.
        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertOk();
    }

    /**
     * A reset is the remedy for a compromised account, so sessions issued
     * before it must not survive.
     */
    public function test_reset_revokes_existing_tokens(): void
    {
        Notification::fake();

        $user = User::query()->where('email', 'coordinator@fyp.local')->firstOrFail();
        Sanctum::actingAs($user);
        $user->createToken('web');

        $this->assertGreaterThan(0, $user->tokens()->count());

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'token' => 'whatever',
            'email' => 'coordinator@fyp.local',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
