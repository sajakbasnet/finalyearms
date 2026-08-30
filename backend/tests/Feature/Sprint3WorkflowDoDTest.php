<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProposalVersion;
use App\Models\Role;
use App\Models\Student;
use App\Models\SupervisorAssignment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 3 Definition of Done, end to end:
 *
 *   Student → Team → Supervisor → Proposal → Supervisor Review
 *
 * Driven entirely over HTTP as the people involved: a lead forms a team, three
 * classmates accept, the team asks a supervisor, the supervisor accepts, the
 * lead writes and submits a proposal, and the supervisor reviews it.
 */
final class Sprint3WorkflowDoDTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Teacher $supervisor;

    /** @var array<int, Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->department = Department::query()->where('code', 'CS')->firstOrFail();
        $this->supervisor = Teacher::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'teacher@fyp.local'))
            ->firstOrFail();

        // Teams are 4-6, so the seeded pair is not enough to form one.
        foreach (range(1, 5) as $n) {
            $this->students[$n] = $this->makeStudent("member{$n}@fyp.local", "Member {$n}", "CS-2027-00{$n}");
        }
    }

    private function makeStudent(string $email, string $name, string $registration): Student
    {
        $user = User::query()->create([
            'role_id' => Role::query()->where('slug', 'student')->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        return Student::query()->create([
            'user_id' => $user->id,
            'department_id' => $this->department->id,
            'academic_session_id' => AcademicSession::query()->value('id'),
            'registration_number' => $registration,
        ]);
    }

    private function actAs(Student $student): void
    {
        Sanctum::actingAs($student->user);
    }

    private function actAsSupervisor(): void
    {
        Sanctum::actingAs($this->supervisor->user);
    }

    public function test_student_to_team_to_supervisor_to_proposal_to_review(): void
    {
        $lead = $this->students[1];

        // ---- 1. A student starts a team project -------------------------
        $this->actAs($lead);

        $this->postJson('/api/student/projects', [
            'title' => 'Adaptive Irrigation Controller',
            'is_team' => true,
            'team_name' => 'Team Hydra',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Team Hydra')
            ->assertJsonPath('data.is_lead', true)
            ->assertJsonPath('data.member_count', 1);

        // ---- 2. Three classmates are invited and accept ------------------
        foreach ([2, 3, 4] as $n) {
            $this->actAs($lead);
            $invitationId = $this->postJson('/api/student/team/invitations', [
                'student_id' => $this->students[$n]->id,
                'message' => 'Come build this with us.',
            ])->assertCreated()->json('data.id');

            $this->actAs($this->students[$n]);
            $this->postJson("/api/student/invitations/{$invitationId}/accept")->assertOk();
        }

        $this->actAs($lead);
        $this->getJson('/api/student/team')
            ->assertOk()
            ->assertJsonPath('data.member_count', 4);

        // ---- 3. The team asks a supervisor -------------------------------
        $this->getJson('/api/student/supervisors')
            ->assertOk()
            ->assertJsonPath('data.0.is_available', true);

        $requestId = $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $this->supervisor->id,
            'rationale' => 'Your published work on embedded sensing is exactly the ground our controller stands on.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        // ---- 4. The supervisor accepts -----------------------------------
        $this->actAsSupervisor();

        $this->getJson('/api/teacher/supervisor-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.team.member_count', 4);

        $this->postJson("/api/teacher/supervisor-requests/{$requestId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $project = Project::query()->where('title', 'Adaptive Irrigation Controller')->firstOrFail();
        $this->assertSame($this->supervisor->id, $project->supervisor_id);

        // Every member of THIS team is assigned, so the supervisor's student
        // list, the dashboards and the Coordinator override all keep working.
        // Scoped to the team's own members — the seeded data already has an
        // unrelated student assigned to the same supervisor.
        $memberIds = $project->group->members()->pluck('student_id')->all();
        $this->assertCount(4, $memberIds);

        $this->assertSame(4, SupervisorAssignment::query()
            ->where('teacher_id', $this->supervisor->id)
            ->where('is_active', true)
            ->whereIn('student_id', $memberIds)
            ->count());

        // ---- 5. The lead writes and submits a proposal -------------------
        $this->actAs($lead);

        $this->postJson('/api/student/proposals', [
            'title' => 'Adaptive Irrigation Controller',
            'abstract' => 'A soil-moisture driven irrigation controller that adapts its schedule to local rainfall.',
            'objectives' => 'Reduce water use while holding yield steady.',
            'methodology' => 'Sensor array, edge controller, weekly calibration against manual readings.',
        ])->assertOk();

        $this->postJson('/api/student/proposals/submit')
            ->assertOk()
            ->assertJsonPath('data.status', ProposalStatus::Submitted->value);

        // ---- 6. The supervisor reviews it --------------------------------
        $this->actAsSupervisor();

        $proposalId = $this->getJson('/api/teacher/proposals')
            ->assertOk()
            ->json('data.0.id');

        $this->postJson("/api/teacher/proposals/{$proposalId}/review", [
            'action' => 'approve',
            'comment' => 'Well scoped. Proceed.',
        ])->assertOk();

        $this->assertSame(
            ProposalStatus::Approved,
            ProposalVersion::query()->findOrFail($proposalId)->status,
        );

        // ---- 7. The team can read the whole history ----------------------
        $this->actAs($lead);

        $this->getJson('/api/student/proposals/history')
            ->assertOk()
            ->assertJsonPath('data.0.version_number', 1)
            ->assertJsonPath('data.0.status', ProposalStatus::Approved->value);
    }

    /**
     * The rejection path: a supervisor declines, and the team is free to ask
     * someone else rather than being stuck with the refusal.
     */
    public function test_a_declined_team_can_approach_someone_else(): void
    {
        $lead = $this->students[1];
        $other = Teacher::query()->whereKeyNot($this->supervisor->id)->first()
            ?? Teacher::query()->create([
                'user_id' => User::query()->create([
                    'role_id' => Role::query()->where('slug', 'supervisor')->value('id'),
                    'name' => 'Dr. Second',
                    'email' => 'second@fyp.local',
                    'password' => 'password',
                    'is_active' => true,
                ])->id,
                'department_id' => $this->department->id,
                'employee_id' => 'EMP-900',
                'max_projects' => 5,
            ]);

        // The fallback supervisor must be in the same department to be askable.
        $other->update(['department_id' => $this->department->id]);

        $this->actAs($lead);
        $this->postJson('/api/student/projects', ['title' => 'Solo build', 'is_team' => false])
            ->assertCreated();

        $requestId = $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $this->supervisor->id,
            'rationale' => 'Your area of work lines up closely with what this project needs to do.',
        ])->assertCreated()->json('data.id');

        $this->actAsSupervisor();
        $this->postJson("/api/teacher/supervisor-requests/{$requestId}/decline", [
            'response_note' => 'Already carrying too much this session.',
        ])->assertOk();

        // The decline and its reason are visible, and a new request is allowed.
        $this->actAs($lead);
        $this->getJson('/api/student/supervisor-requests')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'declined')
            ->assertJsonPath('data.0.response_note', 'Already carrying too much this session.');

        $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $other->id,
            'rationale' => 'Approaching you next, since the first supervisor was already at capacity.',
        ])->assertCreated();
    }

    public function test_a_project_stays_in_draft_until_a_supervisor_accepts(): void
    {
        $lead = $this->students[1];
        $this->actAs($lead);

        $this->postJson('/api/student/projects', ['title' => 'Solo build', 'is_team' => false])
            ->assertCreated();

        $project = Project::query()->where('title', 'Solo build')->firstOrFail();
        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertNull($project->supervisor_id);

        $requestId = $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $this->supervisor->id,
            'rationale' => 'This project sits squarely in the area you supervise, and I would value the guidance.',
        ])->assertCreated()->json('data.id');

        $this->actAsSupervisor();
        $this->postJson("/api/teacher/supervisor-requests/{$requestId}/accept")->assertOk();

        $this->assertSame(ProjectStatus::ProposalPending, $project->fresh()->status);
    }
}
