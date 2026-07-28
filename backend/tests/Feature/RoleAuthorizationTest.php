<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_student_routes(): void
    {
        $this->getJson('/api/student/overview')->assertUnauthorized();
        $this->getJson('/api/student/workspace')->assertUnauthorized();
        $this->getJson('/api/student/timeline')->assertUnauthorized();
        $this->getJson('/api/student/notifications')->assertUnauthorized();
    }

    public function test_teacher_cannot_access_student_routes(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'teacher@fyp.local')->firstOrFail());

        $this->getJson('/api/student/overview')->assertForbidden();
        $this->postJson('/api/student/proposals', [
            'title' => 'Hack',
            'abstract' => 'Nope',
        ])->assertForbidden();
    }

    public function test_admin_cannot_access_student_routes(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->getJson('/api/student/overview')->assertForbidden();
        $this->getJson('/api/student/notifications')->assertForbidden();
    }

    public function test_unassigned_student_has_null_supervisor(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student2@fyp.local')->firstOrFail());

        $this->getJson('/api/student/overview')
            ->assertOk()
            ->assertJsonPath('data.supervisor', null)
            ->assertJsonPath('data.project', null);
    }
}
