<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateProjectRequest;
use App\Http\Requests\Student\InviteTeamMemberRequest;
use App\Models\Student;
use App\Models\TeamInvitation;
use App\Services\StudentService;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Project creation and team formation, from the student's side.
 */
final class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teams,
        private readonly StudentService $students,
    ) {}

    /** Starts an individual or team project. */
    public function storeProject(CreateProjectRequest $request): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());

        $project = $this->teams->createProject(
            $student,
            $request->string('title')->toString(),
            $request->boolean('is_team'),
            $request->string('team_name')->toString() ?: null,
        );

        return response()->json([
            'message' => $project->student_group_id === null
                ? 'Individual project created.'
                : 'Team created. Invite members, then request a supervisor.',
            'data' => $this->mapTeam($student),
        ], 201);
    }

    /** The student's team, its members and its outstanding invitations. */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->mapTeam($this->students->resolveStudent($request->user())),
        ]);
    }

    public function invite(InviteTeamMemberRequest $request): JsonResponse
    {
        $lead = $this->students->resolveStudent($request->user());
        $invitee = Student::query()->findOrFail($request->integer('student_id'));

        $invitation = $this->teams->invite(
            $lead,
            $invitee,
            $request->string('message')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => [
                'id' => $invitation->id,
                'student_id' => $invitation->student_id,
                'status' => $invitation->status->value,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /** Invitations awaiting this student's answer. */
    public function invitations(Request $request): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());

        $invitations = TeamInvitation::query()
            ->where('student_id', $student->id)
            ->with(['group.members.student.user', 'inviter.user'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $invitations->map(fn (TeamInvitation $invitation): array => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
                'status_label' => $invitation->status->label(),
                'is_actionable' => $invitation->isActionable(),
                'has_expired' => $invitation->hasExpired(),
                'message' => $invitation->message,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'invited_by' => $invitation->inviter?->user?->name,
                'team' => [
                    'id' => $invitation->group?->id,
                    'name' => $invitation->group?->name,
                    'member_count' => $invitation->group?->memberCount() ?? 0,
                ],
            ]),
        ]);
    }

    public function acceptInvitation(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());
        $this->teams->acceptInvitation($student, $invitation);

        return response()->json([
            'message' => 'You have joined the team.',
            'data' => $this->mapTeam($student),
        ]);
    }

    public function declineInvitation(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $student = $this->students->resolveStudent($request->user());
        $this->teams->declineInvitation($student, $invitation);

        return response()->json(['message' => 'Invitation declined.']);
    }

    /** Removes a member, or leaves the team when acting on yourself. */
    public function removeMember(Request $request, Student $member): JsonResponse
    {
        $actor = $this->students->resolveStudent($request->user());
        $this->teams->removeMember($actor, $member);

        return response()->json([
            'message' => $actor->id === $member->id ? 'You have left the team.' : 'Member removed.',
            'data' => $this->mapTeam($actor),
        ]);
    }

    public function transferLead(Request $request, Student $member): JsonResponse
    {
        $lead = $this->students->resolveStudent($request->user());
        $this->teams->transferLead($lead, $member);

        return response()->json([
            'message' => 'Team lead transferred.',
            'data' => $this->mapTeam($lead->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapTeam(Student $student): ?array
    {
        $group = $student->group();

        if ($group === null) {
            return null;
        }

        $group->load(['members.student.user', 'invitations.student.user', 'supervisor.user']);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'is_individual' => $group->is_individual,
            'member_count' => $group->memberCount(),
            'min_members' => (int) config('projects.team.min_members'),
            'max_members' => (int) config('projects.team.max_members'),
            'is_lead' => $group->leaderStudentId() === $student->id,
            'supervisor' => $group->supervisor === null ? null : [
                'id' => $group->supervisor->id,
                'name' => $group->supervisor->user?->name,
            ],
            'members' => $group->members->map(fn ($member): array => [
                'student_id' => $member->student_id,
                'name' => $member->student?->user?->name,
                'registration_number' => $member->student?->registration_number,
                'is_leader' => $member->is_leader,
            ])->all(),
            'pending_invitations' => $group->invitations
                ->filter(fn (TeamInvitation $i) => $i->isActionable())
                ->map(fn (TeamInvitation $i): array => [
                    'id' => $i->id,
                    'name' => $i->student?->user?->name,
                    'registration_number' => $i->student?->registration_number,
                    'expires_at' => $i->expires_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }
}
