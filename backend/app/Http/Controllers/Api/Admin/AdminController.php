<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSupervisorRequest;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\StoreSessionRequest;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Requests\Admin\UpdateSessionRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    public function departments(): JsonResponse
    {
        $departments = $this->adminService->listDepartments();

        return response()->json([
            'data' => $departments->map(fn (Department $department) => $this->mapDepartment($department)),
        ]);
    }

    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->adminService->createDepartment($request->validated());

        return response()->json([
            'message' => 'Department created successfully.',
            'data' => $this->mapDepartment($department->loadCount(['teachers', 'students'])),
        ], 201);
    }

    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department = $this->adminService->updateDepartment($department, $request->validated());

        return response()->json([
            'message' => 'Department updated successfully.',
            'data' => $this->mapDepartment($department),
        ]);
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        $this->adminService->deleteDepartment($department);

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }

    public function sessions(): JsonResponse
    {
        return response()->json([
            'data' => $this->adminService->listSessions()
                ->map(fn (AcademicSession $session) => $this->mapSession($session)),
        ]);
    }

    public function storeSession(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->adminService->createSession($request->validated());

        return response()->json([
            'message' => 'Academic session created successfully.',
            'data' => $this->mapSession($session),
        ], 201);
    }

    public function updateSession(UpdateSessionRequest $request, AcademicSession $session): JsonResponse
    {
        $session = $this->adminService->updateSession($session, $request->validated());

        return response()->json([
            'message' => 'Academic session updated successfully.',
            'data' => $this->mapSession($session),
        ]);
    }

    /** Makes this the current session and stands every other one down. */
    public function activateSession(AcademicSession $session): JsonResponse
    {
        $session = $this->adminService->activateSession($session);

        return response()->json([
            'message' => "{$session->name} is now the active session.",
            'data' => $this->mapSession($session),
        ]);
    }

    public function destroySession(AcademicSession $session): JsonResponse
    {
        $this->adminService->deleteSession($session);

        return response()->json(['message' => 'Academic session deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSession(AcademicSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'start_date' => $session->start_date?->toDateString(),
            'end_date' => $session->end_date?->toDateString(),
            'is_active' => $session->is_active,
            'dates' => $session->relationLoaded('dates')
                ? $session->dates->map(fn ($date) => [
                    'id' => $date->id,
                    'label' => $date->label,
                    'date' => $date->date?->toDateString(),
                    'is_deadline' => $date->is_deadline,
                ])->all()
                : null,
        ];
    }

    public function proposals(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listProposals(
            filters: [
                'department_id' => $request->integer('department_id') ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
            ],
            perPage: min($request->integer('per_page', 20), 100),
        );

        $items = collect($paginator->items())->map(fn ($proposal) => $this->mapProposal($proposal));

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listStudents(
            filters: [
                'department_id' => $request->integer('department_id') ?: null,
                'search' => $request->string('search')->toString() ?: null,
                'unassigned' => $request->boolean('unassigned'),
            ],
            perPage: min($request->integer('per_page', 50), 100),
        );

        $items = collect($paginator->items())->map(fn (Student $student) => $this->mapStudent($student));

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeStudent(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->adminService->createStudent($request->validated());

        return response()->json([
            'message' => 'Student created successfully.',
            'data' => $this->mapStudent($student),
        ], 201);
    }

    public function updateStudent(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->adminService->updateStudent($student, $request->validated());

        return response()->json([
            'message' => 'Student updated successfully.',
            'data' => $this->mapStudent($student),
        ]);
    }

    public function destroyStudent(Student $student): JsonResponse
    {
        $this->adminService->deleteStudent($student);

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }

    public function teachers(Request $request): JsonResponse
    {
        $teachers = $this->adminService->listTeachers(
            departmentId: $request->integer('department_id') ?: null,
            search: $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'data' => $teachers->map(fn (Teacher $teacher) => $this->mapTeacher($teacher)),
        ]);
    }

    public function storeTeacher(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->adminService->createTeacher($request->validated());

        return response()->json([
            'message' => 'Teacher created successfully.',
            'data' => $this->mapTeacher($teacher->loadCount([
                'assignments as active_students_count' => fn ($q) => $q->where('is_active', true),
            ])),
        ], 201);
    }

    public function updateTeacher(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher = $this->adminService->updateTeacher($teacher, $request->validated());

        return response()->json([
            'message' => 'Teacher updated successfully.',
            'data' => $this->mapTeacher($teacher),
        ]);
    }

    public function destroyTeacher(Teacher $teacher): JsonResponse
    {
        $this->adminService->deleteTeacher($teacher);

        return response()->json([
            'message' => 'Teacher deleted successfully.',
        ]);
    }

    public function assignSupervisor(AssignSupervisorRequest $request, Student $student): JsonResponse
    {
        $teacher = Teacher::query()->findOrFail($request->integer('teacher_id'));
        $assignment = $this->adminService->assignSupervisor($student, $teacher);

        return response()->json([
            'message' => 'Supervisor assigned successfully.',
            'data' => [
                'id' => $assignment->id,
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                'student' => $this->mapStudent($assignment->student),
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->user->name,
                    'email' => $assignment->teacher->user->email,
                    'employee_id' => $assignment->teacher->employee_id,
                    'designation' => $assignment->teacher->designation,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDepartment(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'teachers_count' => $department->teachers_count ?? 0,
            'students_count' => $department->students_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTeacher(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'employee_id' => $teacher->employee_id,
            'designation' => $teacher->designation,
            'max_projects' => $teacher->max_projects,
            'active_students_count' => $teacher->active_students_count ?? 0,
            'name' => $teacher->user?->name,
            'email' => $teacher->user?->email,
            'phone' => $teacher->user?->phone,
            'department' => $teacher->department ? [
                'id' => $teacher->department->id,
                'name' => $teacher->department->name,
                'code' => $teacher->department->code,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProposal(mixed $proposal): array
    {
        $project = $proposal->project;
        $student = $project?->student;
        $supervisor = $project?->supervisor;

        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'version_number' => $proposal->version_number,
            'status' => $proposal->status?->value,
            'status_label' => $proposal->status?->label(),
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'abstract' => $proposal->abstract,
            'project' => $project ? [
                'id' => $project->id,
                'title' => $project->title,
                'domain' => $project->domain,
                'technology_stack' => $project->technology_stack,
                'status' => $project->status?->value,
            ] : null,
            'student' => $student ? [
                'id' => $student->id,
                'name' => $student->user?->name,
                'email' => $student->user?->email,
                'registration_number' => $student->registration_number,
                'department' => $student->department ? [
                    'id' => $student->department->id,
                    'name' => $student->department->name,
                    'code' => $student->department->code,
                ] : null,
            ] : null,
            'supervisor' => $supervisor ? [
                'id' => $supervisor->id,
                'name' => $supervisor->user?->name,
                'email' => $supervisor->user?->email,
            ] : null,
            'academic_session' => $project?->academicSession?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStudent(Student $student): array
    {
        $assignment = $student->supervisorAssignment;
        $teacher = $assignment?->teacher;

        return [
            'id' => $student->id,
            'name' => $student->user?->name,
            'email' => $student->user?->email,
            'phone' => $student->user?->phone,
            'registration_number' => $student->registration_number,
            'roll_number' => $student->roll_number,
            // Name for display, id so an edit form can round-trip the value
            // without blanking it.
            'batch' => $student->batch?->name,
            'batch_id' => $student->batch_id,
            'department' => $student->department ? [
                'id' => $student->department->id,
                'name' => $student->department->name,
                'code' => $student->department->code,
            ] : null,
            'academic_session' => $student->academicSession ? [
                'id' => $student->academicSession->id,
                'name' => $student->academicSession->name,
            ] : null,
            'supervisor' => $teacher ? [
                'id' => $teacher->id,
                'name' => $teacher->user?->name,
                'email' => $teacher->user?->email,
                'employee_id' => $teacher->employee_id,
                'designation' => $teacher->designation,
            ] : null,
        ];
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => $this->adminService->listRoles()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ]),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listUsers(
            filters: [
                'role_slug' => $request->string('role')->toString() ?: null,
                'department_id' => $request->integer('department_id') ?: null,
                'search' => $request->string('search')->toString() ?: null,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            ],
            perPage: min($request->integer('per_page', 25), 100),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (User $user) => $this->mapUser($user)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        $user = $this->adminService->createUser($validated);

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $this->mapUser($user),
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $updated = $this->adminService->updateUser($user, $validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $this->mapUser($updated),
        ]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        $this->adminService->deleteUser($user, (int) $request->user()?->id);

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    public function toggleUserStatus(Request $request, User $user): JsonResponse
    {
        $updated = $this->adminService->toggleUserStatus($user, (int) $request->user()?->id);

        return response()->json([
            'message' => $updated->is_active ? 'User activated successfully.' : 'User deactivated successfully.',
            'data' => $this->mapUser($updated),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUser(User $user): array
    {
        $dept = $user->teacher?->department ?? $user->student?->department;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug,
                'description' => $user->role->description,
            ] : null,
            'department' => $dept ? [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
            ] : null,
            'details' => [
                'employee_id' => $user->teacher?->employee_id,
                'designation' => $user->teacher?->designation,
                'registration_number' => $user->student?->registration_number,
                'roll_number' => $user->student?->roll_number,
                'batch' => $user->student?->batch?->name,
            ],
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
