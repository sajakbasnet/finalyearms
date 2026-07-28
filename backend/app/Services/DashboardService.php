<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ProposalVersion;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;

final readonly class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return match ($user->role?->slug) {
            'admin' => $this->adminDashboard(),
            'teacher' => $this->teacherDashboard($user),
            'student' => $this->studentDashboard($user),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function adminDashboard(): array
    {
        return [
            'cards' => [
                'total_students' => Student::query()->count(),
                'total_teachers' => Teacher::query()->count(),
                'total_projects' => Project::query()->count(),
                'pending_proposals' => ProposalVersion::query()
                    ->whereIn('status', [
                        ProposalStatus::Submitted->value,
                        ProposalStatus::UnderReview->value,
                        ProposalStatus::Resubmitted->value,
                    ])
                    ->count(),
                'approved_projects' => Project::query()
                    ->whereIn('status', [
                        ProjectStatus::InProgress->value,
                        ProjectStatus::Completed->value,
                    ])
                    ->count(),
                'rejected_projects' => Project::query()
                    ->where('status', ProjectStatus::Rejected->value)
                    ->count(),
            ],
            'recent_announcements' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teacherDashboard(User $user): array
    {
        $teacher = $user->teacher;

        if ($teacher === null) {
            return ['cards' => [], 'assigned_students' => []];
        }

        $assignedStudents = $teacher->assignments()
            ->where('is_active', true)
            ->with(['student.user', 'student.department'])
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->student->id,
                'name' => $assignment->student->user->name,
                'registration_number' => $assignment->student->registration_number,
                'department' => $assignment->student->department?->name,
            ]);

        $pendingReviews = ProposalVersion::query()
            ->whereHas('project', fn ($q) => $q->where('supervisor_id', $teacher->id))
            ->whereIn('status', [
                ProposalStatus::Submitted->value,
                ProposalStatus::Resubmitted->value,
                ProposalStatus::UnderReview->value,
            ])
            ->count();

        $approvedProjects = Project::query()
            ->where('supervisor_id', $teacher->id)
            ->whereIn('status', [ProjectStatus::InProgress->value, ProjectStatus::Completed->value])
            ->count();

        return [
            'cards' => [
                'assigned_students' => $assignedStudents->count(),
                'pending_reviews' => $pendingReviews,
                'approved_projects' => $approvedProjects,
                'upcoming_meetings' => 0,
            ],
            'assigned_students' => $assignedStudents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentDashboard(User $user): array
    {
        $student = $user->student;

        if ($student === null) {
            return ['cards' => []];
        }

        $student->load([
            'department',
            'academicSession',
            'supervisorAssignment.teacher.user',
            'supervisorAssignment.teacher.department',
        ]);

        $project = Project::query()
            ->where('student_id', $student->id)
            ->with(['proposalVersions' => fn ($q) => $q->latest('version_number')->limit(1)])
            ->first();

        $supervisor = $student->supervisorAssignment?->teacher;

        $latestProposal = $project?->proposalVersions->first();

        return [
            'cards' => [
                'supervisor' => $supervisor ? [
                    'name' => $supervisor->user->name,
                    'email' => $supervisor->user->email,
                    'designation' => $supervisor->designation,
                    'department' => $supervisor->department?->name,
                ] : null,
                'current_status' => $latestProposal?->status?->label()
                    ?? $project?->status?->label()
                    ?? 'Not started',
                'upcoming_deadline' => null,
                'progress' => $project?->progressReports()->latest()->value('percentage_completed') ?? 0,
            ],
            'project' => $project ? [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status?->value,
                'status_label' => $project->status?->label(),
            ] : null,
            'student' => [
                'registration_number' => $student->registration_number,
                'batch' => $student->batch,
                'department' => $student->department?->name,
                'session' => $student->academicSession?->name,
            ],
        ];
    }
}
