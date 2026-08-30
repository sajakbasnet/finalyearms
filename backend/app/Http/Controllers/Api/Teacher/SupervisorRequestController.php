<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SupervisorRequest;
use App\Services\SupervisorRequestService;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The supervisor's side of the request flow: what has been asked of them, and
 * accepting or declining it.
 */
final class SupervisorRequestController extends Controller
{
    public function __construct(
        private readonly SupervisorRequestService $requests,
        private readonly TeacherService $teachers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teachers->resolveTeacher($request->user());
        $pending = $this->requests->pendingFor($teacher);

        return response()->json([
            'meta' => [
                'active_projects' => $teacher->activeProjectCount(),
                'max_projects' => $teacher->max_projects,
                'remaining_capacity' => $teacher->remainingCapacity(),
            ],
            'data' => $pending->map(fn (SupervisorRequest $r): array => $this->map($r)),
        ]);
    }

    public function accept(Request $request, SupervisorRequest $supervisorRequest): JsonResponse
    {
        $teacher = $this->teachers->resolveTeacher($request->user());
        $accepted = $this->requests->accept($teacher, $supervisorRequest);

        return response()->json([
            'message' => 'Request accepted. The project is now yours to supervise.',
            'data' => $this->map($accepted->load(['project.group.members.student.user', 'requester.user'])),
        ]);
    }

    public function decline(Request $request, SupervisorRequest $supervisorRequest): JsonResponse
    {
        $request->validate([
            'response_note' => ['nullable', 'string', 'max:500'],
        ]);

        $teacher = $this->teachers->resolveTeacher($request->user());

        $declined = $this->requests->decline(
            $teacher,
            $supervisorRequest,
            $request->string('response_note')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Request declined. The team can approach someone else.',
            'data' => $this->map($declined),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(SupervisorRequest $request): array
    {
        $project = $request->project;
        $group = $project?->group;

        return [
            'id' => $request->id,
            'status' => $request->status->value,
            'rationale' => $request->rationale,
            'response_note' => $request->response_note,
            'created_at' => $request->created_at?->toIso8601String(),
            'requested_by' => $request->requester?->user?->name,
            'project' => $project === null ? null : [
                'id' => $project->id,
                'title' => $project->title,
                'is_team' => $project->student_group_id !== null,
            ],
            'team' => $group === null ? null : [
                'id' => $group->id,
                'name' => $group->name,
                'member_count' => $group->members->count(),
                'members' => $group->members->map(fn ($m): array => [
                    'name' => $m->student?->user?->name,
                    'registration_number' => $m->student?->registration_number,
                    'is_leader' => $m->is_leader,
                ])->all(),
            ],
        ];
    }
}
