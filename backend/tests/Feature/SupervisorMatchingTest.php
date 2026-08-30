<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Project;
use App\Models\Role;
use App\Models\Student;
use App\Models\SupervisorRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ProposalDuplicateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SupervisorMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Department $cs;

    private Teacher $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->cs = Department::query()->where('code', 'CS')->firstOrFail();
        $this->supervisor = Teacher::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'teacher@fyp.local'))
            ->firstOrFail();
    }

    private function makeStudent(string $suffix, ?Department $department = null): Student
    {
        $user = User::query()->create([
            'role_id' => Role::query()->where('slug', 'student')->value('id'),
            'name' => 'Student '.$suffix,
            'email' => "s{$suffix}@fyp.local",
            'password' => 'password',
            'is_active' => true,
        ]);

        return Student::query()->create([
            'user_id' => $user->id,
            'department_id' => ($department ?? $this->cs)->id,
            'academic_session_id' => AcademicSession::query()->value('id'),
            'registration_number' => "CS-2027-2{$suffix}",
        ]);
    }

    /** A student with an individual project, ready to request a supervisor. */
    private function studentWithProject(string $suffix, ?Department $department = null): Student
    {
        $student = $this->makeStudent($suffix, $department);

        Sanctum::actingAs($student->user);
        $this->postJson('/api/student/projects', ['title' => "Project {$suffix}", 'is_team' => false])
            ->assertCreated();

        return $student;
    }

    private function request(Student $student, Teacher $teacher): TestResponse
    {
        Sanctum::actingAs($student->user);

        return $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $teacher->id,
            'rationale' => 'Your area of research lines up closely with what this project sets out to do.',
        ]);
    }

    // ---------------------------------------------------------------
    // Directory
    // ---------------------------------------------------------------

    public function test_the_directory_shows_live_availability(): void
    {
        $student = $this->studentWithProject('a');

        $this->getJson('/api/student/supervisors')
            ->assertOk()
            ->assertJsonPath('data.0.max_projects', $this->supervisor->max_projects)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'designation', 'active_projects', 'remaining_capacity', 'is_available']],
            ]);
    }

    /** Cross-department assignment is refused later, so it is not offered. */
    public function test_the_directory_is_scoped_to_the_students_department(): void
    {
        $se = Department::query()->where('code', 'SE')->firstOrFail();
        $student = $this->studentWithProject('b', $se);

        $names = collect($this->getJson('/api/student/supervisors')->assertOk()->json('data'))
            ->pluck('department')
            ->unique();

        $this->assertSame(['Software Engineering'], $names->values()->all());
    }

    public function test_the_directory_can_be_searched_and_filtered(): void
    {
        $this->studentWithProject('c');

        $this->getJson('/api/student/supervisors?search=Sarah')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Dr. Sarah Sharma');

        $this->getJson('/api/student/supervisors?available_only=1')->assertOk();
    }

    // ---------------------------------------------------------------
    // Requesting
    // ---------------------------------------------------------------

    public function test_a_rationale_is_required_and_must_say_something(): void
    {
        $student = $this->studentWithProject('d');
        Sanctum::actingAs($student->user);

        $this->postJson('/api/student/supervisor-requests', [
            'teacher_id' => $this->supervisor->id,
            'rationale' => 'please',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rationale');
    }

    /** Two supervisors could otherwise both accept the same project. */
    public function test_only_one_request_can_be_open_at_a_time(): void
    {
        $student = $this->studentWithProject('e');
        $this->request($student, $this->supervisor)->assertCreated();

        $this->request($student, $this->supervisor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_withdrawing_frees_the_project_to_ask_again(): void
    {
        $student = $this->studentWithProject('f');
        $id = $this->request($student, $this->supervisor)->assertCreated()->json('data.id');

        Sanctum::actingAs($student->user);
        $this->postJson("/api/student/supervisor-requests/{$id}/withdraw")->assertOk();

        $this->request($student, $this->supervisor)->assertCreated();
    }

    public function test_a_supervisor_outside_the_department_cannot_be_asked(): void
    {
        $se = Department::query()->where('code', 'SE')->firstOrFail();
        $student = $this->studentWithProject('g', $se);

        $this->request($student, $this->supervisor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    // ---------------------------------------------------------------
    // Capacity — counted in projects, because a supervisor takes many teams
    // ---------------------------------------------------------------

    public function test_a_supervisor_at_capacity_cannot_be_asked(): void
    {
        $this->supervisor->update(['max_projects' => 1]);

        // The seeded project already occupies their single slot.
        $this->assertSame(1, $this->supervisor->activeProjectCount());

        $student = $this->studentWithProject('h');

        $this->request($student, $this->supervisor)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    public function test_capacity_counts_projects_not_students(): void
    {
        $this->supervisor->update(['max_projects' => 3]);

        // One seeded project, whatever its member count.
        $this->assertSame(1, $this->supervisor->activeProjectCount());
        $this->assertSame(2, $this->supervisor->remainingCapacity());
        $this->assertTrue($this->supervisor->hasCapacity());
    }

    /**
     * Capacity is re-checked at acceptance, not only at request: it may have
     * filled while the request sat waiting.
     */
    public function test_capacity_is_rechecked_when_accepting(): void
    {
        $this->supervisor->update(['max_projects' => 2]);

        $student = $this->studentWithProject('i');
        $id = $this->request($student, $this->supervisor)->assertCreated()->json('data.id');

        // The slot fills while the request is pending.
        $this->supervisor->update(['max_projects' => 1]);

        Sanctum::actingAs($this->supervisor->user);
        $this->postJson("/api/teacher/supervisor-requests/{$id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request');
    }

    public function test_completed_work_does_not_count_against_capacity(): void
    {
        $this->supervisor->update(['max_projects' => 1]);

        Project::query()->where('supervisor_id', $this->supervisor->id)
            ->update(['status' => ProjectStatus::Completed->value]);

        $this->assertSame(0, $this->supervisor->activeProjectCount());

        $student = $this->studentWithProject('j');
        $this->request($student, $this->supervisor)->assertCreated();
    }

    // ---------------------------------------------------------------
    // Accept / decline
    // ---------------------------------------------------------------

    public function test_a_supervisor_cannot_answer_a_request_sent_to_someone_else(): void
    {
        $other = Teacher::query()->whereKeyNot($this->supervisor->id)->firstOrFail();
        $other->update(['department_id' => $this->cs->id]);

        $student = $this->studentWithProject('k');
        $id = $this->request($student, $other)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->supervisor->user);
        $this->postJson("/api/teacher/supervisor-requests/{$id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request');
    }

    public function test_a_settled_request_cannot_be_answered_twice(): void
    {
        $student = $this->studentWithProject('l');
        $id = $this->request($student, $this->supervisor)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->supervisor->user);
        $this->postJson("/api/teacher/supervisor-requests/{$id}/accept")->assertOk();

        $this->postJson("/api/teacher/supervisor-requests/{$id}/decline")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request');
    }

    public function test_declining_records_the_reason_for_the_team(): void
    {
        $student = $this->studentWithProject('m');
        $id = $this->request($student, $this->supervisor)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->supervisor->user);
        $this->postJson("/api/teacher/supervisor-requests/{$id}/decline", [
            'response_note' => 'Outside my area this session.',
        ])->assertOk();

        $this->assertSame(
            'Outside my area this session.',
            SupervisorRequest::query()->findOrFail($id)->response_note,
        );
    }

    /** The Coordinator override still works, and shares the same capacity rule. */
    public function test_a_coordinator_can_override_and_assign_directly(): void
    {
        $student = $this->studentWithProject('n');

        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());

        $this->postJson("/api/admin/students/{$student->id}/assign-supervisor", [
            'teacher_id' => $this->supervisor->id,
        ])->assertOk();

        $this->assertDatabaseHas('supervisor_assignments', [
            'student_id' => $student->id,
            'teacher_id' => $this->supervisor->id,
            'is_active' => true,
        ]);
    }

    public function test_the_coordinator_override_respects_capacity(): void
    {
        $this->supervisor->update(['max_projects' => 1]);
        $student = $this->studentWithProject('o');

        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());

        $this->postJson("/api/admin/students/{$student->id}/assign-supervisor", [
            'teacher_id' => $this->supervisor->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teacher_id');
    }

    // ---------------------------------------------------------------
    // Duplicate detection — a warning, never a block
    // ---------------------------------------------------------------

    public function test_a_near_identical_title_is_flagged(): void
    {
        $matches = app(ProposalDuplicateChecker::class)
            ->check('Smart Campus Attendance System');

        $this->assertNotEmpty($matches);
        $this->assertSame('Smart Campus Attendance System', $matches[0]['title']);
        $this->assertSame(1.0, $matches[0]['title_similarity']);
    }

    public function test_punctuation_and_case_do_not_hide_a_duplicate(): void
    {
        $matches = app(ProposalDuplicateChecker::class)
            ->check('smart campus, attendance system!');

        $this->assertNotEmpty($matches);
    }

    public function test_an_unrelated_title_is_not_flagged(): void
    {
        $matches = app(ProposalDuplicateChecker::class)
            ->check('Groundwater Salinity Mapping in Coastal Wells');

        $this->assertSame([], $matches);
    }

    public function test_a_project_is_never_flagged_against_itself(): void
    {
        $existing = Project::query()->firstOrFail();

        $matches = app(ProposalDuplicateChecker::class)
            ->check((string) $existing->title, null, $existing->id);

        $this->assertSame([], $matches);
    }

    /** The warning rides along with a successful save; it never refuses it. */
    public function test_saving_a_similar_proposal_warns_but_succeeds(): void
    {
        $student = $this->studentWithProject('p');
        Sanctum::actingAs($student->user);

        $this->postJson('/api/student/proposals', [
            'title' => 'Smart Campus Attendance System',
            'abstract' => 'A facial recognition based attendance system for university labs.',
        ])
            ->assertOk()
            ->assertJsonPath('warnings.similar_projects.0.title', 'Smart Campus Attendance System');
    }

    public function test_an_original_proposal_carries_no_warning(): void
    {
        $student = $this->studentWithProject('q');
        Sanctum::actingAs($student->user);

        $this->postJson('/api/student/proposals', [
            'title' => 'Groundwater Salinity Mapping in Coastal Wells',
            'abstract' => 'Mapping chloride intrusion across shallow aquifers using field sampling.',
        ])
            ->assertOk()
            ->assertJsonPath('warnings', null);
    }
}
