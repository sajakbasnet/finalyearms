<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Evaluation;
use App\Models\Milestone;
use App\Models\ProgressComment;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\ProposalComment;
use App\Models\ProposalReply;
use App\Models\ProposalVersion;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class TeacherService
{
    public function resolveTeacher(User $user): Teacher
    {
        $teacher = $user->teacher;

        if ($teacher === null) {
            throw ValidationException::withMessages([
                'teacher' => ['Teacher profile not found for this account.'],
            ]);
        }

        return $teacher;
    }

    /**
     * @return Collection<int, \App\Models\SupervisorAssignment>
     */
    public function assignedStudents(Teacher $teacher): Collection
    {
        return $teacher->assignments()
            ->where('is_active', true)
            ->with([
                'student.user',
                'student.department',
                'student.academicSession',
                'student.projects' => fn ($q) => $q->latest('id')->limit(1),
            ])
            ->latest('assigned_at')
            ->get();
    }

    /**
     * @return Collection<int, ProposalVersion>
     */
    public function listProposals(Teacher $teacher, ?string $status = null): Collection
    {
        $query = ProposalVersion::query()
            ->with([
                'project.student.user',
                'project.student.department',
            ])
            ->whereIn('id', function ($sub) use ($teacher): void {
                $sub->selectRaw('MAX(id)')
                    ->from('proposal_versions')
                    ->whereIn('project_id', Project::query()->where('supervisor_id', $teacher->id)->select('id'))
                    ->groupBy('project_id');
            })
            ->latest('submitted_at')
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getProposal(Teacher $teacher, ProposalVersion $proposal): ProposalVersion
    {
        $this->assertSupervisesProposal($teacher, $proposal);

        return $proposal->load([
            'project.student.user',
            'project.student.department',
            'project.finalSubmission',
            'project.attachments',
            'comments.user',
            'comments.replies.user',
            'submitter',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function reviewProposal(
        Teacher $teacher,
        ProposalVersion $proposal,
        User $actor,
        string $action,
        ?string $comment = null,
        ?string $section = null,
    ): ProposalVersion {
        $this->assertSupervisesProposal($teacher, $proposal);

        if (! in_array($proposal->status, [
            ProposalStatus::Submitted,
            ProposalStatus::UnderReview,
            ProposalStatus::Resubmitted,
            ProposalStatus::RevisionRequested,
        ], true)) {
            throw ValidationException::withMessages([
                'action' => ['This proposal cannot be reviewed in its current status.'],
            ]);
        }

        $status = match ($action) {
            'approve' => ProposalStatus::Approved,
            'reject' => ProposalStatus::Rejected,
            'request_revision' => ProposalStatus::RevisionRequested,
            default => throw ValidationException::withMessages([
                'action' => ['Invalid review action.'],
            ]),
        };

        return DB::transaction(function () use ($teacher, $proposal, $actor, $action, $comment, $section, $status): ProposalVersion {
            $proposal->update(['status' => $status]);

            $projectStatus = match ($action) {
                'approve' => ProjectStatus::InProgress,
                'reject' => ProjectStatus::Rejected,
                default => ProjectStatus::ProposalPending,
            };

            $proposal->project?->update(['status' => $projectStatus]);

            ProposalComment::query()->create([
                'proposal_version_id' => $proposal->id,
                'user_id' => $actor->id,
                'section' => $section,
                'comment' => $comment ?: match ($action) {
                    'approve' => 'Proposal approved.',
                    'reject' => 'Proposal rejected.',
                    default => 'Revision requested.',
                },
                'action' => $action,
            ]);

            return $this->getProposal($teacher, $proposal->fresh());
        });
    }

    public function addComment(
        Teacher $teacher,
        ProposalVersion $proposal,
        User $actor,
        string $comment,
        ?string $section = null,
    ): ProposalComment {
        $this->assertSupervisesProposal($teacher, $proposal);

        if ($proposal->status === ProposalStatus::Submitted) {
            $proposal->update(['status' => ProposalStatus::UnderReview]);
        }

        return ProposalComment::query()->create([
            'proposal_version_id' => $proposal->id,
            'user_id' => $actor->id,
            'section' => $section,
            'comment' => $comment,
            'action' => 'comment',
        ])->load(['user', 'replies.user']);
    }

    public function replyToComment(
        Teacher $teacher,
        ProposalComment $comment,
        User $actor,
        string $reply,
    ): ProposalReply {
        $comment->load('proposalVersion.project');
        $this->assertSupervisesProposal($teacher, $comment->proposalVersion);

        return ProposalReply::query()->create([
            'proposal_comment_id' => $comment->id,
            'user_id' => $actor->id,
            'reply' => $reply,
        ])->load('user');
    }

    /**
     * @return Collection<int, ProgressReport>
     */
    public function listProgressReports(Teacher $teacher): Collection
    {
        return ProgressReport::query()
            ->with(['project.student.user', 'submitter', 'comments.user'])
            ->whereHas('project', fn ($q) => $q->where('supervisor_id', $teacher->id))
            ->latest('submitted_at')
            ->get();
    }

    public function reviewProgressReport(
        Teacher $teacher,
        ProgressReport $report,
        User $actor,
        string $action,
        ?string $comment = null,
    ): ProgressReport {
        $report->load('project');
        $this->assertSupervisesProject($teacher, $report->project);

        $status = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => throw ValidationException::withMessages([
                'action' => ['Invalid progress review action.'],
            ]),
        };

        return DB::transaction(function () use ($report, $actor, $action, $comment, $status): ProgressReport {
            $report->update(['status' => $status]);

            ProgressComment::query()->create([
                'progress_report_id' => $report->id,
                'user_id' => $actor->id,
                'comment' => $comment ?: ($action === 'approve' ? 'Progress report approved.' : 'Progress report rejected.'),
                'action' => $action,
            ]);

            return $report->fresh()->load(['project.student.user', 'submitter', 'comments.user']);
        });
    }

    /**
     * @return Collection<int, Milestone>
     */
    public function listMilestones(Teacher $teacher, Project $project): Collection
    {
        $this->assertSupervisesProject($teacher, $project);

        return $project->milestones()->orderBy('sort_order')->orderBy('due_date')->get();
    }

    public function updateMilestone(
        Teacher $teacher,
        Milestone $milestone,
        string $status,
        ?string $remarks = null,
    ): Milestone {
        $milestone->load('project');
        $this->assertSupervisesProject($teacher, $milestone->project);

        $milestoneStatus = MilestoneStatus::tryFrom($status);
        if ($milestoneStatus === null) {
            throw ValidationException::withMessages([
                'status' => ['Invalid milestone status.'],
            ]);
        }

        $milestone->update([
            'status' => $milestoneStatus,
            'remarks' => $remarks,
        ]);

        return $milestone->fresh();
    }

    /**
     * @param  array{
     *     innovation: int,
     *     implementation: int,
     *     documentation: int,
     *     presentation: int,
     *     testing: int,
     *     remarks?: string|null
     * }  $data
     */
    public function saveEvaluation(Teacher $teacher, Project $project, array $data): Evaluation
    {
        $this->assertSupervisesProject($teacher, $project);

        $overall = (
            $data['innovation']
            + $data['implementation']
            + $data['documentation']
            + $data['presentation']
            + $data['testing']
        ) / 5;

        return Evaluation::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'teacher_id' => $teacher->id,
            ],
            [
                'innovation' => $data['innovation'],
                'implementation' => $data['implementation'],
                'documentation' => $data['documentation'],
                'presentation' => $data['presentation'],
                'testing' => $data['testing'],
                'overall_score' => round($overall, 2),
                'remarks' => $data['remarks'] ?? null,
            ],
        )->load(['project.student.user', 'teacher.user']);
    }

    public function getProjectWorkspace(Teacher $teacher, Project $project): Project
    {
        $this->assertSupervisesProject($teacher, $project);

        return $project->load([
            'student.user',
            'student.department',
            'proposalVersions' => fn ($q) => $q->latest('version_number'),
            'milestones' => fn ($q) => $q->orderBy('sort_order'),
            'progressReports' => fn ($q) => $q->latest('submitted_at'),
            'evaluation',
            'finalSubmission',
            'attachments',
        ]);
    }

    /**
     * @return list<array{key: string, label: string, path: string, type: string}>
     */
    public function listDownloadableFiles(Teacher $teacher, Project $project): array
    {
        $this->assertSupervisesProject($teacher, $project);
        $project->load(['proposalVersions', 'finalSubmission', 'attachments']);

        $files = [];

        foreach ($project->proposalVersions as $proposal) {
            if (filled($proposal->pdf_path)) {
                $files[] = [
                    'key' => 'proposal_'.$proposal->id,
                    'label' => 'Proposal v'.$proposal->version_number.' PDF',
                    'path' => $proposal->pdf_path,
                    'type' => 'proposal',
                ];
            }
        }

        if ($project->finalSubmission) {
            foreach ($project->finalSubmission->downloadableFiles() as $label => $path) {
                $files[] = [
                    'key' => 'final_'.$label,
                    'label' => 'Final: '.str_replace('_', ' ', $label),
                    'path' => $path,
                    'type' => 'final',
                ];
            }
        }

        foreach ($project->attachments as $attachment) {
            $files[] = [
                'key' => 'attachment_'.$attachment->id,
                'label' => $attachment->file_name,
                'path' => $attachment->file_path,
                'type' => 'attachment',
            ];
        }

        return $files;
    }

    public function downloadFile(Teacher $teacher, Project $project, string $path): StreamedResponse
    {
        $files = $this->listDownloadableFiles($teacher, $project);
        $allowed = collect($files)->pluck('path')->all();

        if (! in_array($path, $allowed, true)) {
            throw ValidationException::withMessages([
                'path' => ['File not found for this project.'],
            ]);
        }

        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'path' => ['File is missing from storage.'],
            ]);
        }

        return Storage::disk('local')->download($path);
    }

    private function assertSupervisesProposal(Teacher $teacher, ProposalVersion $proposal): void
    {
        $proposal->loadMissing('project');
        $this->assertSupervisesProject($teacher, $proposal->project);
    }

    private function assertSupervisesProject(Teacher $teacher, ?Project $project): void
    {
        if ($project === null || (int) $project->supervisor_id !== (int) $teacher->id) {
            throw ValidationException::withMessages([
                'project' => ['You are not the supervisor of this project.'],
            ]);
        }
    }
}
