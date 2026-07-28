<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_can_login(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'student@fyp.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'student@fyp.local')
            ->assertJsonPath('user.role.slug', 'student')
            ->assertJsonStructure(['message', 'token', 'token_type', 'user']);
    }

    public function test_teacher_can_login(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'teacher@fyp.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role.slug', 'teacher')
            ->assertJsonPath('user.teacher.employee_id', 'EMP-001');
    }

    public function test_admin_can_login(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@fyp.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role.slug', 'admin');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'student@fyp.local',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student@fyp.local')->firstOrFail());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'student@fyp.local')
            ->assertJsonPath('user.student.registration_number', 'CS-2022-001');
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_user_can_logout(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'email' => 'student@fyp.local',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');
        $user = User::query()->where('email', 'student@fyp.local')->firstOrFail();

        $this->assertGreaterThan(0, $user->tokens()->count());

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertSame(0, $user->fresh()->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_change_password(): void
    {
        $user = User::query()->where('email', 'student2@fyp.local')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'student2@fyp.local',
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student@fyp.local')->firstOrFail());

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'incorrect',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::query()->where('email', 'student2@fyp.local')->firstOrFail();
        $user->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => 'student2@fyp.local',
            'password' => 'password',
        ])->assertUnprocessable();
    }
}
