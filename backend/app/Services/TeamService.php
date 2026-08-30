<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project creation and team formation.
 *
 * Two shapes: an individual project (one student, no group) and a team project
 * (a group of `projects.team.min_members`–`max_members`). Students join a team
 * by accepting an invitation rather than being added, so membership is always
 * something they agreed to.
 */
final class TeamService
{
    /**
     * Starts a project for a student.
     *
     * The student becomes the lead of a team project. A supervisor is not
     * required yet — under the Sprint 3 flow the team forms first and then asks
     * someone to take it on.
     *
     * @throws ValidationException
     */
    public function createProject(Student $student, string $title, bool $isTeam, ?string $teamName = null): Project
    {
        $this->assertNoActiveProject($student);

        return DB::transaction(function () use ($student, $title, $isTeam, $teamName): Project {
            $group = null;

            if ($isTeam) {
                $group = StudentGroup::query()->create([
                    'name' => $teamName ?: $title,
                    'academic_session_id' => $student->academic_session_id,
                    'is_individual' => false,
                ]);

                StudentGroupMember::query()->create([
                    'student_group_id' => $group->id,
                    'student_id' => $student->id,
                    'is_leader' => true,
                ]);
            }

            $project = Project::query()->create([
                'title' => $title,
                'status' => ProjectStatus::Draft,
                'academic_session_id' => $student->academic_session_id,
                'student_group_id' => $group?->id,
                // An individual project hangs off the student directly; a team
                // project hangs off the group.
                'student_id' => $isTeam ? null : $student->id,
            ]);

            $project->members()->create(['student_id' => $student->id]);

            return $project->load(['group.members.student.user']);
        });
    }

    /**
     * Invites a student to a team.
     *
     * @throws ValidationException
     */
    public function invite(Student $lead, Student $invitee, ?string $message = null): TeamInvitation
    {
        $group = $this->assertLeads($lead);

        if ($invitee->id === $lead->id) {
            throw ValidationException::withMessages([
                'student_id' => ['You are already on this team.'],
            ]);
        }

        if ($group->hasMember($invitee->id)) {
            throw ValidationException::withMessages([
                'student_id' => ['That student is already on this team.'],
            ]);
        }

        if ($invitee->department_id !== $lead->department_id) {
            throw ValidationException::withMessages([
                'student_id' => ['Team members must be from the same department.'],
            ]);
        }

        if ($invitee->group() !== null) {
            throw ValidationException::withMessages([
                'student_id' => ['That student already belongs to a team.'],
            ]);
        }

        // Counted against members plus outstanding invitations, so a team
        // cannot invite past the maximum and discover it as people accept.
        $max = (int) config('projects.team.max_members');

        if ($group->committedCount() >= $max) {
            throw ValidationException::withMessages([
                'student_id' => ["A team can hold at most {$max} members, including invitations already sent."],
            ]);
        }

        $expiryDays = (int) config('projects.invitations.expires_after_days');

        // Re-inviting after a decline reuses the row rather than stacking
        // duplicates, which the unique index would refuse anyway.
        return TeamInvitation::query()->updateOrCreate(
            ['student_group_id' => $group->id, 'student_id' => $invitee->id],
            [
                'invited_by' => $lead->id,
                'status' => InvitationStatus::Pending,
                'message' => $message,
                'expires_at' => now()->addDays($expiryDays),
                'responded_at' => null,
            ],
        );
    }

    /**
     * @throws ValidationException
     */
    public function acceptInvitation(Student $student, TeamInvitation $invitation): StudentGroup
    {
        $this->assertInvitee($student, $invitation);

        if (! $invitation->isActionable()) {
            throw ValidationException::withMessages([
                'invitation' => [
                    $invitation->hasExpired()
                        ? 'That invitation has expired. Ask the team lead to send a new one.'
                        : 'That invitation has already been '.$invitation->status->value.'.',
                ],
            ]);
        }

        $this->assertNoActiveProject($student);

        $group = $invitation->group;
        $max = (int) config('projects.team.max_members');

        if ($group->memberCount() >= $max) {
            throw ValidationException::withMessages([
                'invitation' => ["That team is already full ({$max} members)."],
            ]);
        }

        return DB::transaction(function () use ($student, $invitation, $group): StudentGroup {
            StudentGroupMember::query()->create([
                'student_group_id' => $group->id,
                'student_id' => $student->id,
                'is_leader' => false,
            ]);

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'responded_at' => now(),
            ]);

            // Joining the team means joining its project.
            $project = $group->projects()->first();
            $project?->members()->firstOrCreate(['student_id' => $student->id]);

            return $group->fresh(['members.student.user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public function declineInvitation(Student $student, TeamInvitation $invitation): TeamInvitation
    {
        $this->assertInvitee($student, $invitation);

        if (! $invitation->isActionable()) {
            throw ValidationException::withMessages([
                'invitation' => ['That invitation is no longer open.'],
            ]);
        }

        $invitation->update([
            'status' => InvitationStatus::Declined,
            'responded_at' => now(),
        ]);

        return $invitation;
    }

    /**
     * Removes a member, or lets one leave.
     *
     * @throws ValidationException
     */
    public function removeMember(Student $actor, Student $target): StudentGroup
    {
        $group = $actor->group();

        if ($group === null || ! $group->hasMember($target->id)) {
            throw ValidationException::withMessages([
                'student_id' => ['That student is not on your team.'],
            ]);
        }

        $isLead = $group->leaderStudentId() === $actor->id;

        if (! $isLead && $actor->id !== $target->id) {
            throw ValidationException::withMessages([
                'student_id' => ['Only the team lead can remove another member.'],
            ]);
        }

        if ($group->leaderStudentId() === $target->id) {
            throw ValidationException::withMessages([
                'student_id' => ['Transfer the lead role before removing the lead.'],
            ]);
        }

        // A team that has already asked for a supervisor cannot quietly change
        // shape underneath the request.
        if ($group->projects()->whereNotIn('status', [ProjectStatus::Draft->value])->exists()) {
            throw ValidationException::withMessages([
                'student_id' => ['Membership is fixed once the project has left draft. Ask the coordinator.'],
            ]);
        }

        return DB::transaction(function () use ($group, $target): StudentGroup {
            $group->members()->where('student_id', $target->id)->delete();
            $group->projects()->first()?->members()->where('student_id', $target->id)->delete();

            return $group->fresh(['members.student.user']);
        });
    }

    /**
     * @throws ValidationException
     */
    public function transferLead(Student $lead, Student $target): StudentGroup
    {
        $group = $this->assertLeads($lead);

        if (! $group->hasMember($target->id)) {
            throw ValidationException::withMessages([
                'student_id' => ['That student is not on your team.'],
            ]);
        }

        return DB::transaction(function () use ($group, $lead, $target): StudentGroup {
            $group->members()->where('student_id', $lead->id)->update(['is_leader' => false]);
            $group->members()->where('student_id', $target->id)->update(['is_leader' => true]);

            return $group->fresh(['members.student.user']);
        });
    }

    /**
     * Whether a team has enough members to go looking for a supervisor.
     *
     * @throws ValidationException
     */
    public function assertReadyForSupervisor(Project $project): void
    {
        if ($project->student_group_id === null) {
            return; // An individual project is always the right size.
        }

        $min = (int) config('projects.team.min_members');
        $count = $project->group?->memberCount() ?? 0;

        if ($count < $min) {
            throw ValidationException::withMessages([
                'team' => ["A team needs at least {$min} members before requesting a supervisor; yours has {$count}."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertLeads(Student $student): StudentGroup
    {
        $group = $student->group();

        if ($group === null) {
            throw ValidationException::withMessages([
                'team' => ['You are not on a team.'],
            ]);
        }

        if ($group->leaderStudentId() !== $student->id) {
            throw ValidationException::withMessages([
                'team' => ['Only the team lead can do that.'],
            ]);
        }

        return $group;
    }

    /**
     * @throws ValidationException
     */
    private function assertInvitee(Student $student, TeamInvitation $invitation): void
    {
        if ((int) $invitation->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'invitation' => ['That invitation was not sent to you.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertNoActiveProject(Student $student): void
    {
        $limit = (int) config('projects.max_active_projects_per_student');

        $active = Project::query()
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Rejected->value])
            ->where(function ($query) use ($student): void {
                $query->where('student_id', $student->id)
                    ->orWhereHas('members', fn ($q) => $q->where('student_id', $student->id));
            })
            ->count();

        if ($active >= $limit) {
            throw ValidationException::withMessages([
                'project' => ['You already have an active project this session.'],
            ]);
        }
    }
}
