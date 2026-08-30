<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Project;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Defines one Gate per permission key in config/rbac.php, so routes can be
 * guarded with `can:<key>` and there is no second place to register a
 * permission.
 *
 * Two layers of check:
 *
 *  1. Does the role hold the key at all (config/rbac.php).
 *  2. For scoped keys, does this user actually own/supervise/lead the subject.
 *
 * The second layer matters because route model binding is unscoped: holding
 * `proposal.review` must not let a supervisor review someone else's proposal.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Keys whose Gate additionally verifies the subject passed to it.
     *
     * @var array<string, string>
     */
    private const SCOPED = [
        'team.member.manage' => 'group',
        'team.act' => 'group',
        'proposal.view.assigned' => 'supervises',
        'proposal.review' => 'supervises',
        'proposal.comment' => 'supervises',
        'progress.view.assigned' => 'supervises',
        'progress.review' => 'supervises',
        'milestone.manage' => 'supervises',
        'evaluation.manage' => 'supervises',
        'meeting.log' => 'supervises',
        'project.view.assigned' => 'supervises',
        'project.file.download' => 'supervises',
        'proposal.view.own' => 'owns',
        'proposal.submit' => 'owns',
        'timeline.view.own' => 'owns',
        'final-submission.submit' => 'owns',
        'progress.submit' => 'owns',
    ];

    public function boot(): void
    {
        /** @var array<string, string> $catalogue */
        $catalogue = config('rbac.permissions', []);

        foreach (array_keys($catalogue) as $permission) {
            Gate::define(
                $permission,
                fn (User $user, mixed $subject = null): bool => $this->allows($user, $permission, $subject),
            );
        }
    }

    private function allows(User $user, string $permission, mixed $subject): bool
    {
        $group = $subject instanceof StudentGroup ? $subject : null;

        if (! $user->hasPermission($permission, $group)) {
            return false;
        }

        $scope = self::SCOPED[$permission] ?? null;

        // Unscoped permission, or no subject supplied to check against. Callers
        // that pass no subject get the role-level answer only; controllers are
        // responsible for passing the subject on scoped checks.
        if ($scope === null || $subject === null) {
            return true;
        }

        return match ($scope) {
            'group' => $group !== null && $user->leadsGroup($group),
            'supervises' => $this->supervises($user, $subject),
            'owns' => $this->owns($user, $subject),
            default => false,
        };
    }

    /** Whether the user is the supervisor of the project the subject belongs to. */
    private function supervises(User $user, mixed $subject): bool
    {
        $teacherId = $user->teacher?->id;
        $project = $this->projectFor($subject);

        return $teacherId !== null
            && $project !== null
            && (int) $project->supervisor_id === (int) $teacherId;
    }

    /** Whether the subject belongs to the user's own project or group. */
    private function owns(User $user, mixed $subject): bool
    {
        $studentId = $user->student?->id;
        $project = $this->projectFor($subject);

        if ($studentId === null || $project === null) {
            return false;
        }

        if ((int) $project->student_id === (int) $studentId) {
            return true;
        }

        // Group projects: any member owns the work.
        return $project->student_group_id !== null
            && $project->group?->members()
                ->where('student_id', $studentId)
                ->exists() === true;
    }

    /** Resolves the project a subject hangs off, for ownership checks. */
    private function projectFor(mixed $subject): ?Project
    {
        if ($subject instanceof Project) {
            return $subject;
        }

        if (is_object($subject) && method_exists($subject, 'project')) {
            $project = $subject->project;

            return $project instanceof Project ? $project : null;
        }

        return null;
    }
}
