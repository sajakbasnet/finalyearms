<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_admin_dashboard_returns_summary_cards(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'total_students',
                        'total_teachers',
                        'total_projects',
                        'pending_proposals',
                        'approved_projects',
                        'rejected_projects',
                    ],
                ],
            ])
            ->assertJsonPath('data.cards.total_students', 2)
            ->assertJsonPath('data.cards.total_teachers', 2);
    }

    public function test_teacher_dashboard_returns_assigned_students(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'teacher@fyp.local')->firstOrFail());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'assigned_students',
                        'pending_reviews',
                        'approved_projects',
                        'upcoming_meetings',
                    ],
                    'assigned_students',
                ],
            ])
            ->assertJsonPath('data.cards.assigned_students', 1)
            ->assertJsonPath('data.assigned_students.0.registration_number', 'CS-2022-001');
    }

    public function test_student_dashboard_returns_supervisor_and_project(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student@fyp.local')->firstOrFail());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'supervisor',
                        'current_status',
                        'upcoming_deadline',
                        'progress',
                    ],
                    'project',
                    'student',
                ],
            ])
            ->assertJsonPath('data.cards.supervisor.email', 'teacher@fyp.local')
            ->assertJsonPath('data.student.registration_number', 'CS-2022-001')
            ->assertJsonPath('data.project.title', 'Smart Campus Attendance System');
    }
}
