<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API-level authorization across the role matrix.
 *
 * Covers the separation the access model requires: Institution Admin owns
 * accounts and org structure, Coordinator owns templates and supervision
 * overrides, and neither can do the other's job.
 */
final class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function actAs(string $email): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------
    // Institution Admin
    // ---------------------------------------------------------------

    /**
     * Every assignable role needs a demo account.
     *
     * The login page offers one shortcut per role. When `admin` and `teacher`
     * were renamed, the Coordinator was left without one — the portal existed
     * but there was no way in from that page. This holds the seeder side of
     * that pairing.
     */
    public function test_every_assignable_role_has_a_demo_account(): void
    {
        $missing = [];

        foreach (UserRole::assignable() as $role) {
            // Employer has no feature surface yet, so no demo account for it.
            if ($role === UserRole::Employer) {
                continue;
            }

            $exists = User::query()
                ->whereHas('role', fn ($query) => $query->where('slug', $role->value))
                ->exists();

            if (! $exists) {
                $missing[] = $role->value;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Demo data is missing an account for: '.implode(', ', $missing)
                .'. Add one, and a matching shortcut on the login page.',
        );
    }

    public function test_institution_admin_manages_accounts_and_structure(): void
    {
        $this->actAs('admin@fyp.local');

        $this->getJson('/api/admin/departments')->assertOk();
        $this->getJson('/api/admin/students')->assertOk();
        $this->getJson('/api/admin/teachers')->assertOk();
        $this->getJson('/api/admin/sessions')->assertOk();
    }

    public function test_institution_admin_cannot_manage_coordinator_templates(): void
    {
        $this->actAs('admin@fyp.local');

        // Templates are the Coordinator's remit.
        $this->getJson('/api/coordinator/project-types')->assertForbidden();
        $this->postJson('/api/coordinator/project-types', ['name' => 'Sneaky'])->assertForbidden();
        $this->getJson('/api/coordinator/activity-templates')->assertForbidden();
    }

    public function test_institution_admin_cannot_override_supervisor_assignment(): void
    {
        $this->actAs('admin@fyp.local');
        $student = Student::query()->firstOrFail();

        $this->postJson("/api/admin/students/{$student->id}/assign-supervisor", [
            'teacher_id' => 1,
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Coordinator
    // ---------------------------------------------------------------

    public function test_coordinator_manages_templates_and_supervision(): void
    {
        $this->actAs('coordinator@fyp.local');

        $this->getJson('/api/coordinator/project-types')->assertOk();
        $this->getJson('/api/coordinator/activity-templates')->assertOk();
        $this->getJson('/api/admin/proposals')->assertOk();
    }

    public function test_coordinator_cannot_manage_accounts_or_structure(): void
    {
        $this->actAs('coordinator@fyp.local');

        // Read-only on reference data, but no account or org mutations.
        $this->getJson('/api/admin/departments')->assertOk();
        $this->postJson('/api/admin/departments', [
            'name' => 'Civil', 'code' => 'CE',
        ])->assertForbidden();
        $this->postJson('/api/admin/students', [])->assertForbidden();
        $this->postJson('/api/admin/teachers', [])->assertForbidden();
    }

    public function test_coordinator_cannot_update_branding(): void
    {
        $this->actAs('coordinator@fyp.local');

        $this->postJson('/api/admin/branding', [
            'institution_name' => 'Rebranded',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Supervisor
    // ---------------------------------------------------------------

    public function test_supervisor_reaches_only_supervision_endpoints(): void
    {
        $this->actAs('teacher@fyp.local');

        $this->getJson('/api/teacher/proposals')->assertOk();
        $this->getJson('/api/teacher/progress-reports')->assertOk();

        $this->getJson('/api/coordinator/project-types')->assertForbidden();
        $this->postJson('/api/admin/students', [])->assertForbidden();
        $this->getJson('/api/student/overview')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Student and the student workspace boundary
    // ---------------------------------------------------------------

    public function test_student_reaches_only_their_own_workspace(): void
    {
        $this->actAs('student@fyp.local');

        $this->getJson('/api/student/overview')->assertOk();
        $this->getJson('/api/student/timeline')->assertOk();

        $this->getJson('/api/teacher/proposals')->assertForbidden();
        $this->getJson('/api/coordinator/project-types')->assertForbidden();
        $this->getJson('/api/admin/students')->assertForbidden();
    }

    /**
     * Regression: every role holds notification.view.own, so guarding the
     * student notification routes on that key alone let an Institution Admin
     * into endpoints that resolve a Student profile.
     */
    public function test_non_students_cannot_reach_student_notification_routes(): void
    {
        foreach (['admin@fyp.local', 'coordinator@fyp.local', 'teacher@fyp.local'] as $email) {
            $this->actAs($email);

            $this->getJson('/api/student/notifications')->assertForbidden();
            $this->postJson('/api/student/notifications/read-all')->assertForbidden();
        }
    }

    // ---------------------------------------------------------------
    // Team Lead — contextual
    // ---------------------------------------------------------------

    public function test_team_lead_permissions_apply_only_to_the_group_they_lead(): void
    {
        $user = User::query()->where('email', 'student@fyp.local')->firstOrFail()->load('role', 'student');
        $student = $user->student;
        $session = $student->academic_session_id;

        $led = StudentGroup::query()->create([
            'name' => 'Team Alpha',
            'academic_session_id' => $session,
            'is_individual' => false,
        ]);
        StudentGroupMember::query()->create([
            'student_group_id' => $led->id,
            'student_id' => $student->id,
            'is_leader' => true,
        ]);

        $memberOnly = StudentGroup::query()->create([
            'name' => 'Team Beta',
            'academic_session_id' => $session,
            'is_individual' => false,
        ]);
        StudentGroupMember::query()->create([
            'student_group_id' => $memberOnly->id,
            'student_id' => $student->id,
            'is_leader' => false,
        ]);

        $this->assertTrue($user->leadsGroup($led));
        $this->assertFalse($user->leadsGroup($memberOnly));

        // The whole point of contextual Team Lead: leading one group must not
        // confer team-lead rights over another.
        $this->assertTrue($user->hasPermission('team.member.manage', $led));
        $this->assertFalse($user->hasPermission('team.member.manage', $memberOnly));

        // Still a Student throughout.
        $this->assertTrue($user->isStudent());
    }

    // ---------------------------------------------------------------
    // Employer
    // ---------------------------------------------------------------

    public function test_employer_has_no_reach_into_institution_endpoints(): void
    {
        $employer = User::query()->create([
            'role_id' => Role::query()->where('slug', 'employer')->value('id'),
            'name' => 'Host Company',
            'email' => 'employer@partner.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        Sanctum::actingAs($employer);

        $this->getJson('/api/admin/students')->assertForbidden();
        $this->getJson('/api/coordinator/project-types')->assertForbidden();
        $this->getJson('/api/teacher/proposals')->assertForbidden();
        $this->getJson('/api/student/overview')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Unauthenticated
    // ---------------------------------------------------------------

    public function test_guests_are_rejected_from_every_guarded_prefix(): void
    {
        $this->getJson('/api/admin/students')->assertUnauthorized();
        $this->getJson('/api/coordinator/project-types')->assertUnauthorized();
        $this->getJson('/api/teacher/proposals')->assertUnauthorized();
        $this->getJson('/api/student/overview')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_a_deactivated_user_cannot_use_an_existing_token(): void
    {
        $user = $this->actAs('coordinator@fyp.local');
        $this->getJson('/api/coordinator/project-types')->assertOk();

        $user->update(['is_active' => false]);

        // Deactivation must take effect immediately, not at next login.
        $this->getJson('/api/coordinator/project-types')->assertForbidden();
    }
}
