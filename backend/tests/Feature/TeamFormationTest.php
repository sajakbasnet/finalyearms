<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TeamFormationTest extends TestCase
{
    use RefreshDatabase;

    private Department $cs;

    /** @var array<int, Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cs = Department::query()->where('code', 'CS')->firstOrFail();

        foreach (range(1, 8) as $n) {
            $this->students[$n] = $this->makeStudent("m{$n}@fyp.local", "CS-2027-10{$n}", $this->cs);
        }
    }

    private function makeStudent(string $email, string $registration, Department $department): Student
    {
        $user = User::query()->create([
            'role_id' => Role::query()->where('slug', 'student')->value('id'),
            'name' => 'Student '.$registration,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        return Student::query()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'academic_session_id' => AcademicSession::query()->value('id'),
            'registration_number' => $registration,
        ]);
    }

    private function actAs(Student $student): void
    {
        Sanctum::actingAs($student->user);
    }

    private function startTeam(Student $lead, string $title = 'Team project'): void
    {
        $this->actAs($lead);
        $this->postJson('/api/student/projects', ['title' => $title, 'is_team' => true])
            ->assertCreated();
    }

    private function inviteAndAccept(Student $lead, Student $invitee): void
    {
        $this->actAs($lead);
        $id = $this->postJson('/api/student/team/invitations', ['student_id' => $invitee->id])
            ->assertCreated()->json('data.id');

        $this->actAs($invitee);
        $this->postJson("/api/student/invitations/{$id}/accept")->assertOk();
    }

    // ---------------------------------------------------------------
    // Creating a project
    // ---------------------------------------------------------------

    public function test_a_student_can_start_an_individual_project(): void
    {
        $this->actAs($this->students[1]);

        $this->postJson('/api/student/projects', ['title' => 'Solo work', 'is_team' => false])
            ->assertCreated()
            // An individual project has no team, so there is nothing to show.
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('projects', ['title' => 'Solo work', 'student_group_id' => null]);
    }

    public function test_starting_a_team_makes_the_creator_the_lead(): void
    {
        $this->startTeam($this->students[1]);

        $this->getJson('/api/student/team')
            ->assertOk()
            ->assertJsonPath('data.is_lead', true)
            ->assertJsonPath('data.members.0.is_leader', true);
    }

    /** One active project per student per session. */
    public function test_a_student_cannot_run_two_projects_at_once(): void
    {
        $this->actAs($this->students[1]);

        $this->postJson('/api/student/projects', ['title' => 'First', 'is_team' => false])
            ->assertCreated();

        $this->postJson('/api/student/projects', ['title' => 'Second', 'is_team' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project');
    }

    // ---------------------------------------------------------------
    // Invitations
    // ---------------------------------------------------------------

    public function test_only_the_lead_can_invite(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->actAs($this->students[2]);
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[3]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team');
    }

    public function test_an_invitee_can_accept(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->actAs($this->students[1]);
        $this->getJson('/api/student/team')
            ->assertOk()
            ->assertJsonPath('data.member_count', 2);
    }

    public function test_an_invitee_can_decline(): void
    {
        $this->startTeam($this->students[1]);

        $this->actAs($this->students[1]);
        $id = $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[2]->id])
            ->assertCreated()->json('data.id');

        $this->actAs($this->students[2]);
        $this->postJson("/api/student/invitations/{$id}/decline")->assertOk();

        $this->assertSame('declined', TeamInvitation::query()->findOrFail($id)->status->value);

        $this->actAs($this->students[1]);
        $this->getJson('/api/student/team')->assertJsonPath('data.member_count', 1);
    }

    /** An invitation is between two people; nobody else may act on it. */
    public function test_a_third_party_cannot_accept_someone_elses_invitation(): void
    {
        $this->startTeam($this->students[1]);

        $this->actAs($this->students[1]);
        $id = $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[2]->id])
            ->assertCreated()->json('data.id');

        $this->actAs($this->students[3]);
        $this->postJson("/api/student/invitations/{$id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitation');
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $this->startTeam($this->students[1]);

        $this->actAs($this->students[1]);
        $id = $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[2]->id])
            ->assertCreated()->json('data.id');

        TeamInvitation::query()->findOrFail($id)->update(['expires_at' => now()->subDay()]);

        $this->actAs($this->students[2]);
        $this->postJson("/api/student/invitations/{$id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitation');
    }

    public function test_a_student_already_on_a_team_cannot_be_invited(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->startTeam($this->students[3], 'Rival team');

        $this->actAs($this->students[3]);
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[2]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_members_must_share_a_department(): void
    {
        $se = Department::query()->where('code', 'SE')->firstOrFail();
        $outsider = $this->makeStudent('outsider@fyp.local', 'SE-2027-999', $se);

        $this->startTeam($this->students[1]);

        $this->actAs($this->students[1]);
        $this->postJson('/api/student/team/invitations', ['student_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    // ---------------------------------------------------------------
    // Size rules
    // ---------------------------------------------------------------

    /**
     * Outstanding invitations count towards the limit, so a team cannot invite
     * its way past the maximum and only discover it as people accept.
     */
    public function test_pending_invitations_count_towards_the_maximum(): void
    {
        config(['projects.team.max_members' => 3]);
        $this->startTeam($this->students[1]);

        $this->actAs($this->students[1]);
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[2]->id])
            ->assertCreated();
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[3]->id])
            ->assertCreated();

        // 1 member + 2 pending = 3, the maximum.
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[4]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    public function test_a_team_cannot_exceed_the_maximum(): void
    {
        config(['projects.team.max_members' => 4]);
        $this->startTeam($this->students[1]);

        foreach ([2, 3, 4] as $n) {
            $this->inviteAndAccept($this->students[1], $this->students[$n]);
        }

        $this->actAs($this->students[1]);
        $this->postJson('/api/student/team/invitations', ['student_id' => $this->students[5]->id])
            ->assertUnprocessable();
    }

    /** A team below the minimum is not ready to approach a supervisor. */
    public function test_an_undersized_team_cannot_request_a_supervisor(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $teacher = Teacher::query()
            ->where('department_id', $this->cs->id)->firstOrFail();

        $this->actAs($this->students[1]);
        $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $teacher->id,
            'rationale' => 'We would like to work with you on this project for the coming session.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team');
    }

    // ---------------------------------------------------------------
    // Membership management
    // ---------------------------------------------------------------

    public function test_the_lead_can_remove_a_member(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->actAs($this->students[1]);
        $this->deleteJson("/api/student/team/members/{$this->students[2]->id}")
            ->assertOk()
            ->assertJsonPath('data.member_count', 1);
    }

    public function test_a_member_can_leave_but_cannot_remove_others(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);
        $this->inviteAndAccept($this->students[1], $this->students[3]);

        // Removing someone else is the lead's call.
        $this->actAs($this->students[2]);
        $this->deleteJson("/api/student/team/members/{$this->students[3]->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        // Leaving is their own.
        $this->deleteJson("/api/student/team/members/{$this->students[2]->id}")->assertOk();
    }

    public function test_the_lead_cannot_be_removed_without_transferring_first(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->actAs($this->students[1]);
        $this->deleteJson("/api/student/team/members/{$this->students[1]->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');

        $this->postJson("/api/student/team/lead/{$this->students[2]->id}")->assertOk();

        $group = StudentGroup::query()->latest('id')->firstOrFail();
        $this->assertSame($this->students[2]->id, $group->leaderStudentId());
    }

    public function test_leaving_frees_the_student_to_join_another_team(): void
    {
        $this->startTeam($this->students[1]);
        $this->inviteAndAccept($this->students[1], $this->students[2]);

        $this->actAs($this->students[2]);
        $this->deleteJson("/api/student/team/members/{$this->students[2]->id}")->assertOk();

        $this->startTeam($this->students[3], 'Another team');
        $this->inviteAndAccept($this->students[3], $this->students[2]);

        $this->actAs($this->students[3]);
        $this->getJson('/api/student/team')->assertJsonPath('data.member_count', 2);
    }
}
