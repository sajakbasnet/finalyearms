<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RequestSupervisorRequest;
use App\Models\SupervisorRequest;
use App\Models\Teacher;
use App\Services\StudentService;
use App\Services\SupervisorRequestService;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The supervisor directory and the request side of the accept/decline flow.
 */
final class SupervisorRequestController extends Controller
{
    public function __construct(
        private readonly SupervisorRequestService $requests,
        private readonly StudentService $students,
        private readonly TeamService $teams,
    ) {}

    /** Supervisors in the student's department, with live availability. */
    public function directory(Request $request): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());

        $supervisors = $this->requests->directory($student, [
            'search' => $request->string('search')->toString(),
            'designation' => $request->string('designation')->toString(),
            'available_only' => $request->boolean('available_only'),
        ]);

        return response()->json([
            'data' => $supervisors->map(fn (Teacher $teacher): array => [
                'id' => $teacher->id,
                'name' => $teacher->user?->name,
                'email' => $teacher->user?->email,
                'designation' => $teacher->designation,
                'department' => $teacher->department?->name,
                'max_projects' => $teacher->max_projects,
                'active_projects' => $teacher->getAttribute('active_projects'),
                'remaining_capacity' => $teacher->getAttribute('remaining_capacity'),
                'supervisee_count' => $teacher->getAttribute('supervisee_count'),
                'is_available' => $teacher->getAttribute('is_available'),
            ])->values(),
        ]);
    }

    /** Requests this project has made, newest first. */
    public function index(Request $request): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());
        $project = $student->group()?->projects()->first()
            ?? $student->projects()->latest('id')->first();

        if ($project === null) {
            return response()->json(['data' => []]);
        }

        $requests = $project->supervisorRequests()
            ->with(['teacher.user', 'requester.user'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $requests->map(fn (SupervisorRequest $r): array => $this->map($r)),
        ]);
    }

    public function store(RequestSupervisorRequest $request): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());
        $project = $student->group()?->projects()->first()
            ?? $student->projects()->latest('id')->firstOrFail();

        $teacher = Teacher::query()->findOrFail($request->integer('teacher_id'));

        $created = $this->requests->request(
            $student,
            $project,
            $teacher,
            $request->string('rationale')->toString(),
        );

        return response()->json([
            'message' => 'Request sent. You will be notified when they respond.',
            'data' => $this->map($created->load(['teacher.user', 'requester.user'])),
        ], 201);
    }

    public function withdraw(Request $request, SupervisorRequest $supervisorRequest): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());
        $this->requests->withdraw($student, $supervisorRequest);

        return response()->json(['message' => 'Request withdrawn. You can approach someone else.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(SupervisorRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'rationale' => $request->rationale,
            'response_note' => $request->response_note,
            'responded_at' => $request->responded_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
            'supervisor' => [
                'id' => $request->teacher?->id,
                'name' => $request->teacher?->user?->name,
                'designation' => $request->teacher?->designation,
            ],
            'requested_by' => $request->requester?->user?->name,
        ];
    }
}
