<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\SupervisorRequestStatus;
use App\Models\Project;
use App\Models\Student;
use App\Models\SupervisorAssignment;
use App\Models\SupervisorRequest;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The supervisor directory, and the request/accept/decline flow.
 *
 * Requests are per project — a team asks once, as a team. Capacity is counted
 * in projects, because a supervisor carries several teams and a team is several
 * students.
 */
final class SupervisorRequestService
{
    public function __construct(
        private readonly TeamService $teams,
    ) {}

    /**
     * Supervisors a student can approach.
     *
     * Scoped to their department, because an assignment across departments is
     * refused later anyway — offering them would only invite a dead end.
     *
     * @param  array<string, mixed>  $filters
     */
    public function directory(Student $student, array $filters = []): Collection
    {
        return Teacher::query()
            ->with(['user:id,name,email', 'department:id,name,code'])
            ->where('department_id', $student->department_id)
            ->when(
                ! empty($filters['search']),
                fn ($query) => $query->whereHas(
                    'user',
                    fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'),
                ),
            )
            ->when(
                ! empty($filters['designation']),
                fn ($query) => $query->where('designation', 'like', '%'.$filters['designation'].'%'),
            )
            ->get()
            // Availability is computed rather than stored, so it cannot drift
            // from the projects actually being supervised.
            ->map(function (Teacher $teacher): Teacher {
                $teacher->setAttribute('active_projects', $teacher->activeProjectCount());
                $teacher->setAttribute('remaining_capacity', $teacher->remainingCapacity());
                $teacher->setAttribute('is_available', $teacher->hasCapacity());
                $teacher->setAttribute('supervisee_count', $teacher->superviseeCount());

                return $teacher;
            })
            ->when(
                ! empty($filters['available_only']),
                fn ($collection) => $collection->filter(fn (Teacher $t) => $t->getAttribute('is_available'))->values(),
            );
    }

    /**
     * Asks a supervisor to take a project on.
     *
     * @throws ValidationException
     */
    public function request(Student $student, Project $project, Teacher $teacher, string $rationale): SupervisorRequest
    {
        $this->assertCanActFor($student, $project);

        if ($project->supervisor_id !== null) {
            throw ValidationException::withMessages([
                'project' => ['This project already has a supervisor.'],
            ]);
        }

        $this->teams->assertReadyForSupervisor($project);

        if ($teacher->department_id !== $student->department_id) {
            throw ValidationException::withMessages([
                'teacher_id' => ['You can only request a supervisor from your own department.'],
            ]);
        }

        // One open request at a time: asking several supervisors at once would
        // let two accept and leave the project double-assigned.
        $open = $project->supervisorRequests()->pending()->first();

        if ($open !== null) {
            throw ValidationException::withMessages([
                'teacher_id' => ['This project already has a request awaiting a response. Withdraw it first.'],
            ]);
        }

        if (! $teacher->hasCapacity()) {
            throw ValidationException::withMessages([
                'teacher_id' => [
                    "This supervisor is at capacity ({$teacher->activeProjectCount()} of {$teacher->max_projects} projects).",
                ],
            ]);
        }

        return SupervisorRequest::query()->create([
            'project_id' => $project->id,
            'teacher_id' => $teacher->id,
            'requested_by' => $student->id,
            'rationale' => $rationale,
            'status' => SupervisorRequestStatus::Pending,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function withdraw(Student $student, SupervisorRequest $request): SupervisorRequest
    {
        $this->assertCanActFor($student, $request->project);

        if (! $request->status->isOpen()) {
            throw ValidationException::withMessages([
                'request' => ['That request has already been '.$request->status->value.'.'],
            ]);
        }

        $request->update([
            'status' => SupervisorRequestStatus::Withdrawn,
            'responded_at' => now(),
        ]);

        return $request;
    }

    /**
     * Accepts a request and takes the project on.
     *
     * Writes one `supervisor_assignments` row per member as well as setting the
     * project and group supervisor, so everything already reading assignments —
     * the supervisor's student list, dashboards, the Coordinator's override —
     * keeps working unchanged.
     *
     * @throws ValidationException
     */
    public function accept(Teacher $teacher, SupervisorRequest $request): SupervisorRequest
    {
        $this->assertAddressedTo($teacher, $request);

        $project = $request->project;

        if ($project->supervisor_id !== null) {
            throw ValidationException::withMessages([
                'request' => ['That project already has a supervisor.'],
            ]);
        }

        // Re-checked at acceptance, not just at request: capacity may have
        // filled while this one sat waiting.
        if (! $teacher->hasCapacity($project->id)) {
            throw ValidationException::withMessages([
                'request' => [
                    "You are at capacity ({$teacher->activeProjectCount()} of {$teacher->max_projects} projects). Decline this or free a slot first.",
                ],
            ]);
        }

        return DB::transaction(function () use ($teacher, $request, $project): SupervisorRequest {
            $request->update([
                'status' => SupervisorRequestStatus::Accepted,
                'responded_at' => now(),
            ]);

            $project->update([
                'supervisor_id' => $teacher->id,
                'status' => ProjectStatus::ProposalPending,
            ]);

            $project->group?->update(['supervisor_id' => $teacher->id]);

            foreach ($project->studentIds() as $studentId) {
                SupervisorAssignment::query()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'academic_session_id' => $project->academic_session_id,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'assigned_at' => now(),
                        'is_active' => true,
                    ],
                );
            }

            return $request->fresh(['project.group', 'teacher.user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public function decline(Teacher $teacher, SupervisorRequest $request, ?string $note = null): SupervisorRequest
    {
        $this->assertAddressedTo($teacher, $request);

        $request->update([
            'status' => SupervisorRequestStatus::Declined,
            'response_note' => $note,
            'responded_at' => now(),
        ]);

        // The project is free to ask elsewhere immediately.
        return $request;
    }

    /**
     * Requests awaiting this supervisor's decision.
     */
    public function pendingFor(Teacher $teacher): Collection
    {
        return SupervisorRequest::query()
            ->where('teacher_id', $teacher->id)
            ->pending()
            ->with([
                'project.group.members.student.user',
                'project.student.user',
                'requester.user',
            ])
            ->latest()
            ->get();
    }

    /**
     * @throws ValidationException
     */
    private function assertCanActFor(Student $student, ?Project $project): void
    {
        if ($project === null) {
            throw ValidationException::withMessages([
                'project' => ['That project no longer exists.'],
            ]);
        }

        // On a team project only the lead speaks for the team; on an individual
        // project the student speaks for themselves.
        if ($project->student_group_id !== null) {
            if ($project->group?->leaderStudentId() !== $student->id) {
                throw ValidationException::withMessages([
                    'project' => ['Only the team lead can manage supervisor requests.'],
                ]);
            }

            return;
        }

        if ((int) $project->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'project' => ['That is not your project.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertAddressedTo(Teacher $teacher, SupervisorRequest $request): void
    {
        if ((int) $request->teacher_id !== (int) $teacher->id) {
            throw ValidationException::withMessages([
                'request' => ['That request was not sent to you.'],
            ]);
        }

        if (! $request->status->isOpen()) {
            throw ValidationException::withMessages([
                'request' => ['That request has already been '.$request->status->value.'.'],
            ]);
        }
    }
}
