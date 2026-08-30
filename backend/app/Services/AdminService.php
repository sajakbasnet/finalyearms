<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProposalVersion;
use App\Models\Role;
use App\Models\Student;
use App\Models\SupervisorAssignment;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AdminService
{
    /**
     * @return Collection<int, Department>
     */
    public function listDepartments(): Collection
    {
        return Department::query()
            ->withCount(['teachers', 'students'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, code: string, description?: string|null}  $data
     */
    public function createDepartment(array $data): Department
    {
        return Department::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * @param  array{name: string, code: string, description?: string|null}  $data
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        $department->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
        ]);

        return $department->fresh()->loadCount(['teachers', 'students']);
    }

    /**
     * @throws ValidationException
     */
    public function deleteDepartment(Department $department): void
    {
        if ($department->teachers()->exists() || $department->students()->exists()) {
            throw ValidationException::withMessages([
                'department' => ['Cannot delete a department that still has teachers or students.'],
            ]);
        }

        $department->delete();
    }

    /**
     * @return Collection<int, AcademicSession>
     */
    public function listSessions(): Collection
    {
        return AcademicSession::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Updates a session, honouring the single-active rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSession(AcademicSession $session, array $data): AcademicSession
    {
        return DB::transaction(function () use ($session, $data): AcademicSession {
            if (! empty($data['is_active'])) {
                AcademicSession::query()->whereKeyNot($session->id)->update(['is_active' => false]);
            }

            $session->update($data);

            return $session->fresh(['dates']);
        });
    }

    /**
     * Makes one session current and stands every other one down.
     *
     * Exactly one session is active at a time — students, groups and projects
     * all pin to it, so two would make "the current session" ambiguous.
     */
    public function activateSession(AcademicSession $session): AcademicSession
    {
        return DB::transaction(function () use ($session): AcademicSession {
            AcademicSession::query()->whereKeyNot($session->id)->update(['is_active' => false]);
            $session->update(['is_active' => true]);

            return $session->fresh(['dates']);
        });
    }

    /**
     * Deletes a session that nothing depends on.
     *
     * Students, groups, assignments and projects all reference a session with
     * restrictOnDelete, so this refuses rather than letting the database throw
     * an opaque constraint error.
     *
     * @throws ValidationException
     */
    public function deleteSession(AcademicSession $session): void
    {
        $blockers = [
            'student' => $session->students()->count(),
            'project' => $session->projects()->count(),
        ];

        $inUse = array_filter($blockers);

        if ($inUse !== []) {
            $parts = [];
            foreach ($inUse as $thing => $count) {
                $parts[] = "{$count} {$thing}".($count === 1 ? '' : 's');
            }

            throw ValidationException::withMessages([
                'session' => 'Cannot delete a session still in use by '.implode(' and ', $parts)
                    .'. Deactivate it instead.',
            ]);
        }

        if ($session->is_active) {
            throw ValidationException::withMessages([
                'session' => 'Cannot delete the active session. Activate another one first.',
            ]);
        }

        $session->delete();
    }

    /**
     * @param  array{name: string, start_date: string, end_date: string, is_active?: bool}  $data
     */
    public function createSession(array $data): AcademicSession
    {
        return DB::transaction(function () use ($data): AcademicSession {
            if (! empty($data['is_active'])) {
                AcademicSession::query()->update(['is_active' => false]);
            }

            return AcademicSession::query()->create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
        });
    }

    /**
     * @param  array{department_id?: int|null, status?: string|null, search?: string|null}  $filters
     */
    public function listProposals(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProposalVersion::query()
            ->with([
                'project.student.user',
                'project.student.department',
                'project.supervisor.user',
                'project.academicSession',
            ])
            ->whereIn('id', function ($subQuery): void {
                $subQuery->selectRaw('MAX(id)')
                    ->from('proposal_versions')
                    ->groupBy('project_id');
            })
            ->latest('submitted_at')
            ->latest('id');

        if (! empty($filters['department_id'])) {
            $query->whereHas('project.student', function ($q) use ($filters): void {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('project.student.user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('project.student', function ($studentQuery) use ($search): void {
                        $studentQuery->where('registration_number', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array{department_id?: int|null, search?: string|null, unassigned?: bool}  $filters
     */
    public function listStudents(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Student::query()
            ->with([
                'user',
                'department',
                'academicSession',
                'supervisorAssignment.teacher.user',
                'supervisorAssignment.teacher.department',
            ])
            ->latest('id');

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('batch', function ($batchQuery) use ($search): void {
                        $batchQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['unassigned'])) {
            $query->whereDoesntHave('supervisorAssignment');
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function listTeachers(?int $departmentId = null, ?string $search = null): Collection
    {
        $query = Teacher::query()
            ->with(['user', 'department'])
            ->withCount([
                'assignments as active_students_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderBy('employee_id');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     password: string,
     *     employee_id: string,
     *     designation?: string|null,
     *     department_id: int,
     *     max_projects?: int
     * }  $data
     */
    public function createTeacher(array $data): Teacher
    {
        $roleId = (int) Role::query()->where('slug', 'teacher')->firstOrFail()->id;

        return DB::transaction(function () use ($data, $roleId): Teacher {
            $user = User::query()->create([
                'role_id' => $roleId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            return Teacher::query()->create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
                'employee_id' => $data['employee_id'],
                'designation' => $data['designation'] ?? null,
                'max_projects' => $data['max_projects'] ?? 5,
            ])->load(['user', 'department']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     password?: string|null,
     *     employee_id: string,
     *     designation?: string|null,
     *     department_id: int,
     *     max_projects?: int
     * }  $data
     *
     * @throws ValidationException
     */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data): Teacher {
            $departmentChanged = (int) $teacher->department_id !== (int) $data['department_id'];

            if ($departmentChanged && $teacher->assignments()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'department_id' => ['Cannot change department while the teacher has active supervised students. Reassign them first.'],
                ]);
            }

            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            $teacher->user->update($userData);

            $teacher->update([
                'department_id' => $data['department_id'],
                'employee_id' => $data['employee_id'],
                'designation' => $data['designation'] ?? null,
                'max_projects' => $data['max_projects'] ?? $teacher->max_projects,
            ]);

            return $teacher->fresh()->load(['user', 'department'])
                ->loadCount(['assignments as active_students_count' => fn ($q) => $q->where('is_active', true)]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteTeacher(Teacher $teacher): void
    {
        if ($teacher->assignments()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'teacher' => ['Cannot delete a teacher with active student assignments.'],
            ]);
        }

        DB::transaction(function () use ($teacher): void {
            $user = $teacher->user;
            $teacher->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     password: string,
     *     registration_number: string,
     *     roll_number?: string|null,
     *     batch?: string|null,
     *     department_id: int,
     *     academic_session_id: int
     * }  $data
     */
    public function createStudent(array $data): Student
    {
        $roleId = (int) Role::query()->where('slug', 'student')->firstOrFail()->id;

        return DB::transaction(function () use ($data, $roleId): Student {
            $user = User::query()->create([
                'role_id' => $roleId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            return Student::query()->create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
                'academic_session_id' => $data['academic_session_id'],
                'registration_number' => $data['registration_number'],
                'roll_number' => $data['roll_number'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
            ])->load(['user', 'department', 'academicSession', 'batch', 'supervisorAssignment.teacher.user']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     phone?: string|null,
     *     password?: string|null,
     *     registration_number: string,
     *     roll_number?: string|null,
     *     batch?: string|null,
     *     department_id: int,
     *     academic_session_id: int
     * }  $data
     *
     * @throws ValidationException
     */
    public function updateStudent(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $departmentChanged = (int) $student->department_id !== (int) $data['department_id'];

            if ($departmentChanged && $student->supervisorAssignment()->exists()) {
                SupervisorAssignment::query()
                    ->where('student_id', $student->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                Project::query()
                    ->where('student_id', $student->id)
                    ->update(['supervisor_id' => null]);
            }

            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            $student->user->update($userData);

            $student->update([
                'department_id' => $data['department_id'],
                'academic_session_id' => $data['academic_session_id'],
                'registration_number' => $data['registration_number'],
                'roll_number' => $data['roll_number'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
            ]);

            return $student->fresh()->load([
                'user',
                'department',
                'academicSession',
                'supervisorAssignment.teacher.user',
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteStudent(Student $student): void
    {
        if ($student->projects()->exists()) {
            throw ValidationException::withMessages([
                'student' => ['Cannot delete a student who already has a project. Remove the project first.'],
            ]);
        }

        DB::transaction(function () use ($student): void {
            SupervisorAssignment::query()->where('student_id', $student->id)->delete();
            $user = $student->user;
            $student->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    public function assignSupervisor(Student $student, Teacher $teacher): SupervisorAssignment
    {
        if ($teacher->department_id !== $student->department_id) {
            throw ValidationException::withMessages([
                'teacher_id' => ['Teacher must belong to the same department as the student.'],
            ]);
        }

        // Capacity is counted in projects: a supervisor takes several teams,
        // and a team is several students. The student's own project is excluded
        // so re-confirming an existing assignment never trips the limit.
        $currentProjectId = $student->projects()->value('id');

        if (! $teacher->hasCapacity($currentProjectId)) {
            throw ValidationException::withMessages([
                'teacher_id' => [
                    "This supervisor is already carrying {$teacher->activeProjectCount()} of a maximum {$teacher->max_projects} projects.",
                ],
            ]);
        }

        return DB::transaction(function () use ($student, $teacher): SupervisorAssignment {
            SupervisorAssignment::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $assignment = SupervisorAssignment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_session_id' => $student->academic_session_id,
                ],
                [
                    'teacher_id' => $teacher->id,
                    'assigned_at' => now(),
                    'is_active' => true,
                ],
            );

            Project::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $student->academic_session_id)
                ->update(['supervisor_id' => $teacher->id]);

            return $assignment->load([
                'student.user',
                'student.department',
                'teacher.user',
                'teacher.department',
                'academicSession',
            ]);
        });
    }
}
