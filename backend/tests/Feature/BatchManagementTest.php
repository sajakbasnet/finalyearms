<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
    }

    private function cs(): Department
    {
        return Department::query()->where('code', 'CS')->firstOrFail();
    }

    /**
     * The full payload the edit form submits, with `batch_id` overridden.
     *
     * PUT replaces the whole student rather than patching it, so a partial body
     * is rejected — the form always sends every field.
     *
     * @return array<string, mixed>
     */
    private function studentPayload(Student $student, ?int $batchId): array
    {
        return [
            'name' => $student->user->name,
            'email' => $student->user->email,
            'registration_number' => $student->registration_number,
            'department_id' => $student->department_id,
            'academic_session_id' => $student->academic_session_id,
            'batch_id' => $batchId,
        ];
    }

    public function test_an_admin_can_list_batches_with_student_counts(): void
    {
        $this->asAdmin();

        $this->getJson('/api/admin/batches')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', '2022 Intake')
            ->assertJsonPath('data.0.students_count', 1);
    }

    public function test_batches_can_be_filtered_by_department(): void
    {
        $this->asAdmin();

        $this->getJson('/api/admin/batches?department_id='.$this->cs()->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.department.code', 'CS');
    }

    public function test_an_admin_can_create_a_batch(): void
    {
        $this->asAdmin();

        $this->postJson('/api/admin/batches', [
            'department_id' => $this->cs()->id,
            'name' => '2023 Intake',
            'intake_year' => 2023,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', '2023 Intake')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('batches', ['name' => '2023 Intake', 'intake_year' => 2023]);
    }

    /**
     * The same cohort name in two departments is normal — "2079 Intake" exists
     * in Computer Science and Software Engineering independently.
     */
    public function test_the_same_name_is_allowed_in_a_different_department(): void
    {
        $this->asAdmin();
        $se = Department::query()->where('code', 'SE')->firstOrFail();

        $this->postJson('/api/admin/batches', [
            'department_id' => $se->id,
            'name' => '2023 Intake',
            'intake_year' => 2023,
        ])->assertCreated();

        $this->postJson('/api/admin/batches', [
            'department_id' => $this->cs()->id,
            'name' => '2023 Intake',
            'intake_year' => 2023,
        ])->assertCreated();

        $this->assertSame(2, Batch::query()->where('name', '2023 Intake')->count());
    }

    public function test_a_duplicate_name_within_a_department_is_rejected(): void
    {
        $this->asAdmin();

        $this->postJson('/api/admin/batches', [
            'department_id' => $this->cs()->id,
            'name' => '2022 Intake',
            'intake_year' => 2022,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_an_implausible_intake_year_is_rejected(): void
    {
        $this->asAdmin();

        $this->postJson('/api/admin/batches', [
            'department_id' => $this->cs()->id,
            'name' => 'Far future',
            'intake_year' => 3000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('intake_year');
    }

    public function test_an_admin_can_rename_a_batch(): void
    {
        $this->asAdmin();
        $batch = Batch::query()->where('department_id', $this->cs()->id)->firstOrFail();

        $this->putJson("/api/admin/batches/{$batch->id}", ['name' => 'Class of 2022'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Class of 2022');
    }

    /**
     * Deleting a batch students belong to would strip their cohort with no way
     * to recover which one it was, so it is retired instead.
     */
    public function test_deleting_a_batch_with_students_deactivates_it(): void
    {
        $this->asAdmin();
        $batch = Batch::query()->where('department_id', $this->cs()->id)->firstOrFail();

        $this->assertGreaterThan(0, $batch->students()->count());

        $this->deleteJson("/api/admin/batches/{$batch->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'is_active' => false]);
    }

    public function test_deleting_an_empty_batch_removes_it(): void
    {
        $this->asAdmin();

        $batch = Batch::query()->create([
            'department_id' => $this->cs()->id,
            'name' => 'Unused',
            'intake_year' => 2024,
        ]);

        $this->deleteJson("/api/admin/batches/{$batch->id}")->assertOk();

        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
    }

    public function test_a_student_carries_their_batch_through_the_api(): void
    {
        $this->asAdmin();

        $this->getJson('/api/admin/students')
            ->assertOk()
            ->assertJsonPath('data.0.batch', '2022 Intake');
    }

    /**
     * The edit form reads `batch_id` back and posts it again. Returning only
     * the name would blank a student's batch on every save.
     */
    public function test_a_student_payload_round_trips_the_batch_id(): void
    {
        $this->asAdmin();
        $student = Student::query()->firstOrFail();

        // Locate this student in the response rather than assuming a position;
        // list ordering is not part of the contract being tested.
        $rows = $this->getJson('/api/admin/students')->assertOk()->json('data');
        $row = collect($rows)->firstWhere('registration_number', $student->registration_number);

        $this->assertNotNull($row, 'The student was not present in the listing.');
        $this->assertSame($student->batch_id, $row['batch_id']);
        $this->assertSame($student->batch->name, $row['batch']);

        $batchId = $row['batch_id'];

        // Posting it straight back leaves the student where they were.
        $this->putJson(
            "/api/admin/students/{$student->id}",
            $this->studentPayload($student, $batchId),
        )
            ->assertOk()
            ->assertJsonPath('data.batch', '2022 Intake');
    }

    public function test_a_student_can_be_moved_out_of_every_batch(): void
    {
        $this->asAdmin();
        $student = Student::query()->firstOrFail();

        $this->putJson("/api/admin/students/{$student->id}", $this->studentPayload($student, null))
            ->assertOk()
            ->assertJsonPath('data.batch', null);

        $this->assertNull($student->fresh()->batch_id);
    }

    public function test_students_can_be_searched_by_batch_name(): void
    {
        $this->asAdmin();

        $this->getJson('/api/admin/students?search=2022 Intake')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_student_cannot_be_assigned_a_nonexistent_batch(): void
    {
        $this->asAdmin();
        $student = Student::query()->firstOrFail();

        $this->putJson("/api/admin/students/{$student->id}", $this->studentPayload($student, 9999))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('batch_id');
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_a_coordinator_can_read_batches_but_not_change_them(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());

        // Coordinators need batch context for oversight, but org structure is
        // the Institution Admin's remit.
        $this->getJson('/api/admin/batches')->assertOk();
        $this->postJson('/api/admin/batches', [
            'department_id' => $this->cs()->id,
            'name' => 'Sneaky',
            'intake_year' => 2024,
        ])->assertForbidden();
    }

    public function test_a_student_cannot_reach_batches_at_all(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student@fyp.local')->firstOrFail());

        $this->getJson('/api/admin/batches')->assertForbidden();
    }
}
