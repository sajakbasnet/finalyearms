<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('email', 'admin@fyp.local')->firstOrFail();
    }

    public function test_admin_can_list_roles(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description']]]);

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('institution_admin', $slugs);
        $this->assertContains('coordinator', $slugs);
        $this->assertContains('supervisor', $slugs);
        $this->assertContains('student', $slugs);
        $this->assertContains('employer', $slugs);
    }

    public function test_admin_can_list_users(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'is_active']],
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_admin_can_create_user_with_role(): void
    {
        Sanctum::actingAs($this->admin);

        $coordinatorRole = Role::query()->where('slug', UserRole::Coordinator->value)->firstOrFail();
        $department = Department::query()->firstOrFail();

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Dr. Coordinator',
            'email' => 'new-coordinator@fyp.local',
            'password' => 'SecurePass123!',
            'role_id' => $coordinatorRole->id,
            'department_id' => $department->id,
            'employee_id' => 'EMP-TEST-99',
            'designation' => 'Associate Professor',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Dr. Coordinator')
            ->assertJsonPath('data.role.slug', 'coordinator');

        $this->assertDatabaseHas('users', ['email' => 'new-coordinator@fyp.local']);
    }

    public function test_admin_can_update_user(): void
    {
        Sanctum::actingAs($this->admin);

        $employerRole = Role::query()->where('slug', UserRole::Employer->value)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $employerRole->id,
            'name' => 'Old Name',
            'email' => 'old-email@fyp.local',
        ]);

        $response = $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'New Name',
            'email' => 'old-email@fyp.local',
            'role_id' => $employerRole->id,
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_admin_cannot_delete_self(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/admin/users/{$this->admin->id}");

        $response->assertStatus(422);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        Sanctum::actingAs($this->admin);

        $employerRole = Role::query()->where('slug', UserRole::Employer->value)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $employerRole->id,
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/admin/users/{$user->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($user->fresh()->is_active);
    }
}
