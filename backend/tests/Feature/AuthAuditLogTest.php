<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuthEvent;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuthAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        RateLimiter::clear('login:coordinator@fyp.local|127.0.0.1');
    }

    private function latest(): ?AuthAuditLog
    {
        return AuthAuditLog::query()->latest('id')->first();
    }

    public function test_successful_login_is_recorded(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertOk();

        $log = $this->latest();

        $this->assertSame(AuthEvent::LoginSucceeded, $log?->event);
        $this->assertTrue($log?->succeeded);
        $this->assertSame('coordinator@fyp.local', $log?->email);
        $this->assertSame('coordinator', $log?->context['role'] ?? null);
        $this->assertNotNull($log?->user_id);
        $this->assertNotNull($log?->ip_address);
    }

    public function test_failed_login_is_recorded_with_a_reason(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $log = $this->latest();

        $this->assertSame(AuthEvent::LoginFailed, $log?->event);
        $this->assertFalse($log?->succeeded);
        $this->assertSame('Incorrect password', $log?->reason);
    }

    /**
     * A failed attempt against an address with no account is exactly the case
     * an audit trail exists for, so it must still produce a row even though
     * there is no user to attribute it to.
     */
    public function test_failed_login_for_an_unknown_account_is_recorded(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'ghost@fyp.local',
            'password' => 'whatever',
        ])->assertUnprocessable();

        $log = $this->latest();

        $this->assertSame(AuthEvent::LoginFailed, $log?->event);
        $this->assertNull($log?->user_id);
        $this->assertSame('ghost@fyp.local', $log?->email);
        $this->assertSame('No such account', $log?->reason);
    }

    public function test_a_deactivated_account_login_is_recorded(): void
    {
        User::query()->where('email', 'coordinator@fyp.local')->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertSame('Account deactivated', $this->latest()?->reason);
    }

    public function test_repeated_failures_lock_the_account_and_record_it(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'coordinator@fyp.local',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        // The sixth attempt is refused by the lockout, not by a password check.
        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertUnprocessable();

        $log = $this->latest();

        $this->assertSame(AuthEvent::LoginBlocked, $log?->event);
        $this->assertSame('Too many failed attempts', $log?->reason);
        $this->assertIsInt($log?->context['retry_after_seconds'] ?? null);
    }

    public function test_a_successful_login_clears_the_lockout_counter(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/auth/login', [
                'email' => 'coordinator@fyp.local',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertOk();

        // Counter reset, so a fresh run of failures is needed to lock out.
        foreach (range(1, 4) as $ignored) {
            $this->postJson('/api/auth/login', [
                'email' => 'coordinator@fyp.local',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->assertSame(AuthEvent::LoginFailed, $this->latest()?->event);
    }

    public function test_logout_is_recorded(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(AuthEvent::Logout, $this->latest()?->event);
    }

    public function test_password_reset_request_and_completion_are_recorded(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'coordinator@fyp.local',
        ])->assertOk();

        $this->assertSame(AuthEvent::PasswordResetRequested, $this->latest()?->event);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid',
            'email' => 'coordinator@fyp.local',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ])->assertUnprocessable();

        $this->assertSame(AuthEvent::PasswordResetFailed, $this->latest()?->event);
    }

    public function test_password_change_is_recorded(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'password' => 'another-secure-password-1',
            'password_confirmation' => 'another-secure-password-1',
        ])->assertOk();

        $this->assertSame(AuthEvent::PasswordChanged, $this->latest()?->event);
    }

    public function test_audit_rows_are_append_only(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'coordinator@fyp.local',
            'password' => 'password',
        ])->assertOk();

        $log = $this->latest();

        $this->assertNotNull($log?->created_at);
        // No updated_at column: the trail is written once and never amended.
        $this->assertNull(AuthAuditLog::UPDATED_AT);
    }
}
