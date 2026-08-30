<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\FinalSubmission;
use App\Models\Milestone;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\ProposalComment;
use App\Models\ProposalReply;
use App\Models\ProposalVersion;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StudentService
{
    public function resolveStudent(User $user): Student
    {
        $student = $user->student;

        if ($student === null) {
            throw ValidationException::withMessages([
                'student' => ['Student profile not found for this account.'],
            ]);
        }

        return $student->load([
            'department',
            'academicSession',
            'supervisorAssignment.teacher.user',
            'supervisorAssignment.teacher.department',
        ]);
    }

    public function overview(Student $student): array
    {
        $project = $this->getOrNullProject($student)?->load([
            'supervisor.user',
            'supervisor.department',
            'latestProposal',
            'milestones',
            'finalSubmission',
        ]);

        $supervisor = $student->supervisorAssignment?->teacher;

        return [
            'supervisor' => $supervisor ? [
                'name' => $supervisor->user?->name,
                'email' => $supervisor->user?->email,
                'phone' => $supervisor->user?->phone,
                'designation' => $supervisor->designation,
                'department' => $supervisor->department?->name,
                'employee_id' => $supervisor->employee_id,
            ] : null,
            'student' => [
                'registration_number' => $student->registration_number,
                'batch' => $student->batch?->name,
                'department' => $student->department?->name,
                'session' => $student->academicSession?->name,
            ],
            'project' => $project ? [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status?->value,
                'status_label' => $project->status?->label(),
                'proposal_status' => $project->latestProposal?->status?->value,
                'proposal_status_label' => $project->latestProposal?->status?->label(),
            ] : null,
        ];
    }

    public function getProjectWorkspace(Student $student): ?Project
    {
        return $this->getOrNullProject($student)?->load([
            'supervisor.user',
            'proposalVersions.comments.user',
            'proposalVersions.comments.replies.user',
            'milestones' => fn ($q) => $q->orderBy('sort_order'),
            'progressReports' => fn ($q) => $q->latest('submitted_at')->with('comments.user'),
            'finalSubmission',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProposalDraft(Student $student, User $actor, array $data, ?UploadedFile $pdf = null): ProposalVersion
    {
        return DB::transaction(function () use ($student, $actor, $data, $pdf): ProposalVersion {
            $project = $this->getOrCreateProject($student, $data);
            $latest = $project->proposalVersions()->latest('version_number')->first();

            if ($latest !== null && ! in_array($latest->status, [
                ProposalStatus::Draft,
                ProposalStatus::RevisionRequested,
            ], true)) {
                throw ValidationException::withMessages([
                    'proposal' => ['You can only edit a proposal while it is in draft or revision-requested status.'],
                ]);
            }

            if ($latest === null || $latest->status === ProposalStatus::RevisionRequested) {
                $versionNumber = $latest ? $latest->version_number + 1 : 1;
                $proposal = ProposalVersion::query()->create([
                    ...$this->proposalPayload($data),
                    'project_id' => $project->id,
                    'version_number' => $versionNumber,
                    'status' => ProposalStatus::Draft,
                    'submitted_by' => $actor->id,
                    'submitted_at' => null,
                ]);
            } else {
                $proposal = $latest;
                $proposal->update($this->proposalPayload($data));
            }

            if ($pdf !== null) {
                $path = $pdf->storeAs(
                    'proposals/'.$project->id,
                    'v'.$proposal->version_number.'-'.time().'.'.$pdf->getClientOriginalExtension(),
                    'local',
                );
                $proposal->update(['pdf_path' => $path]);
            }

            $project->update([
                'title' => $data['title'],
                'description' => $data['abstract'] ?? $project->description,
                'domain' => $data['domain'] ?? $project->domain,
                'technology_stack' => $data['technologies'] ?? $project->technology_stack,
                'status' => ProjectStatus::ProposalPending,
                'supervisor_id' => $project->supervisor_id ?? $student->supervisorAssignment?->teacher_id,
            ]);

            return $proposal->fresh()->load(['comments.user', 'comments.replies.user']);
        });
    }

    public function submitProposal(Student $student, User $actor, ?int $proposalId = null): ProposalVersion
    {
        $project = $this->requireProject($student);
        $proposal = $proposalId
            ? ProposalVersion::query()->where('project_id', $project->id)->findOrFail($proposalId)
            : $project->proposalVersions()->latest('version_number')->first();

        if ($proposal === null) {
            throw ValidationException::withMessages([
                'proposal' => ['Save a draft proposal before submitting.'],
            ]);
        }

        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::RevisionRequested], true)) {
            throw ValidationException::withMessages([
                'proposal' => ['Only draft or revision-requested proposals can be submitted.'],
            ]);
        }

        if (! filled($proposal->title) || ! filled($proposal->abstract)) {
            throw ValidationException::withMessages([
                'proposal' => ['Title and abstract are required before submission.'],
            ]);
        }

        // Drafting needs no supervisor, but submitting does — a proposal is
        // submitted *to* someone for review.
        if ($project->supervisor_id === null) {
            throw ValidationException::withMessages([
                'supervisor' => ['A supervisor must accept your project before you can submit a proposal.'],
            ]);
        }

        $isResubmission = $proposal->status === ProposalStatus::RevisionRequested
            || $proposal->version_number > 1;

        $proposal->update([
            'status' => $isResubmission ? ProposalStatus::Resubmitted : ProposalStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        $this->ensureDefaultMilestones($project);

        if ($project->supervisor?->user) {
            $project->supervisor->user->notify(new PortalNotification([
                'title' => $isResubmission ? 'Proposal resubmitted' : 'Proposal submitted',
                'message' => $actor->name.' submitted "'.$proposal->title.'".',
                'type' => 'proposal_submitted',
                'project_id' => $project->id,
                'proposal_id' => $proposal->id,
            ]));
        }

        $actor->notify(new PortalNotification([
            'title' => 'Proposal submitted',
            'message' => 'Your proposal "'.$proposal->title.'" was submitted successfully.',
            'type' => 'proposal_submitted',
            'project_id' => $project->id,
            'proposal_id' => $proposal->id,
        ]));

        return $proposal->fresh()->load(['comments.user', 'comments.replies.user']);
    }

    public function replyToComment(Student $student, User $actor, ProposalComment $comment, string $reply): ProposalReply
    {
        $comment->load('proposalVersion.project');
        $this->assertOwnsProject($student, $comment->proposalVersion?->project);

        $created = ProposalReply::query()->create([
            'proposal_comment_id' => $comment->id,
            'user_id' => $actor->id,
            'reply' => $reply,
        ])->load('user');

        $teacherUser = $comment->proposalVersion?->project?->supervisor?->user;
        if ($teacherUser) {
            $teacherUser->notify(new PortalNotification([
                'title' => 'Student replied',
                'message' => $actor->name.' replied to your proposal comment.',
                'type' => 'student_replied',
                'proposal_id' => $comment->proposal_version_id,
                'comment_id' => $comment->id,
            ]));
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitProgressReport(Student $student, User $actor, array $data): ProgressReport
    {
        $project = $this->requireProject($student);

        $report = ProgressReport::query()->create([
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'percentage_completed' => $data['percentage_completed'] ?? 0,
            'demo_link' => $data['demo_link'] ?? null,
            'git_repository' => $data['git_repository'] ?? null,
            'status' => 'submitted',
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        if ($project->supervisor?->user) {
            $project->supervisor->user->notify(new PortalNotification([
                'title' => 'Progress report submitted',
                'message' => $actor->name.' submitted "'.$report->title.'".',
                'type' => 'progress_submitted',
                'project_id' => $project->id,
                'progress_report_id' => $report->id,
            ]));
        }

        return $report->load(['comments.user']);
    }

    public function uploadFinalFiles(
        Student $student,
        ?UploadedFile $thesis = null,
        ?UploadedFile $presentation = null,
        ?string $githubRepository = null,
    ): FinalSubmission {
        $project = $this->requireProject($student);

        $submission = FinalSubmission::query()->firstOrNew(['project_id' => $project->id]);

        if ($thesis !== null) {
            $submission->thesis_path = $thesis->storeAs(
                'final/'.$project->id,
                'thesis-'.time().'.'.$thesis->getClientOriginalExtension(),
                'local',
            );
        }

        if ($presentation !== null) {
            $submission->presentation_path = $presentation->storeAs(
                'final/'.$project->id,
                'presentation-'.time().'.'.$presentation->getClientOriginalExtension(),
                'local',
            );
        }

        if ($githubRepository !== null) {
            $submission->github_repository = $githubRepository;
        }

        $submission->submitted_at = now();
        $submission->save();

        $student->user?->notify(new PortalNotification([
            'title' => 'Final files uploaded',
            'message' => 'Your final submission files were saved.',
            'type' => 'final_uploaded',
            'project_id' => $project->id,
        ]));

        if ($project->supervisor?->user) {
            $project->supervisor->user->notify(new PortalNotification([
                'title' => 'Final submission uploaded',
                'message' => ($student->user?->name ?? 'A student').' uploaded final project files.',
                'type' => 'final_uploaded',
                'project_id' => $project->id,
            ]));
        }

        return $submission->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Every version of this project's proposal, newest first.
     *
     * Versions are the record of what was submitted and what came back, so
     * comments and replies are loaded with them rather than fetched per row.
     *
     * @return array<int, array<string, mixed>>
     */
    public function proposalHistory(Student $student): array
    {
        $project = $this->requireProject($student);

        return $project->proposalVersions()
            ->with(['comments.user', 'comments.replies.user', 'submitter'])
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (ProposalVersion $version): array => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'title' => $version->title,
                'abstract' => $version->abstract,
                'status' => $version->status?->value,
                'status_label' => $version->status?->label(),
                'submitted_at' => $version->submitted_at?->toIso8601String(),
                'submitted_by' => $version->submitter?->name,
                'pdf_path' => $version->pdf_path,
                'comments' => $version->comments->map(fn ($comment): array => [
                    'id' => $comment->id,
                    'section' => $comment->section,
                    'comment' => $comment->comment,
                    'action' => $comment->action,
                    'author' => $comment->user?->name,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'replies' => $comment->replies->map(fn ($reply): array => [
                        'id' => $reply->id,
                        'reply' => $reply->reply,
                        'author' => $reply->user?->name,
                        'created_at' => $reply->created_at?->toIso8601String(),
                    ])->all(),
                ])->all(),
            ])
            ->all();
    }

    public function timeline(Student $student): array
    {
        $project = $this->getOrNullProject($student);

        if ($project === null) {
            return [];
        }

        return $project->milestones()
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Milestone $milestone) => [
                'id' => $milestone->id,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'due_date' => $milestone->due_date?->toDateString(),
                'status' => $milestone->status?->value,
                'status_label' => $milestone->status?->label(),
                'remarks' => $milestone->remarks,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notifications(User $user): array
    {
        return $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function markNotificationRead(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->where('id', $notificationId)->firstOrFail();
        $notification->markAsRead();
    }

    public function markAllNotificationsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    /**
     * The student's current project, individual or team.
     *
     * A team project hangs off the group rather than the student, so matching
     * on `student_id` alone would miss it entirely.
     */
    private function getOrNullProject(Student $student): ?Project
    {
        return Project::query()
            ->where(function ($query) use ($student): void {
                $query->where('student_id', $student->id)
                    ->orWhereHas('members', fn ($q) => $q->where('student_id', $student->id));
            })
            ->latest('id')
            ->first();
    }

    private function requireProject(Student $student): Project
    {
        $project = $this->getOrNullProject($student);

        if ($project === null) {
            throw ValidationException::withMessages([
                'project' => ['Create and save a proposal first.'],
            ]);
        }

        return $project->load(['supervisor.user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getOrCreateProject(Student $student, array $data): Project
    {
        $project = $this->getOrNullProject($student);

        if ($project !== null) {
            return $project;
        }

        // No supervisor required: under the team-formation flow a project
        // exists first and then asks someone to take it on. Writing a proposal
        // straight away creates an individual project as a convenience.
        $project = Project::query()->create([
            'title' => $data['title'],
            'description' => $data['abstract'] ?? null,
            'domain' => $data['domain'] ?? null,
            'technology_stack' => $data['technologies'] ?? null,
            'category' => 'Individual',
            'status' => ProjectStatus::Draft,
            'supervisor_id' => $student->supervisorAssignment?->teacher_id,
            'academic_session_id' => $student->academic_session_id,
            'student_id' => $student->id,
        ]);

        $project->members()->create(['student_id' => $student->id]);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function proposalPayload(array $data): array
    {
        return [
            'title' => $data['title'],
            'abstract' => $data['abstract'] ?? null,
            'background' => $data['background'] ?? null,
            'problem_statement' => $data['problem_statement'] ?? null,
            'objectives' => $data['objectives'] ?? null,
            'scope' => $data['scope'] ?? null,
            'methodology' => $data['methodology'] ?? null,
            'literature_review' => $data['literature_review'] ?? null,
            'timeline' => $data['timeline'] ?? null,
            'expected_outcome' => $data['expected_outcome'] ?? null,
            'technologies' => $data['technologies'] ?? null,
            'references' => $data['references'] ?? null,
        ];
    }

    private function assertOwnsProject(Student $student, ?Project $project): void
    {
        if ($project === null || (int) $project->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'project' => ['You do not own this project.'],
            ]);
        }
    }

    private function ensureDefaultMilestones(Project $project): void
    {
        if ($project->milestones()->exists()) {
            return;
        }

        $titles = [
            'Proposal Submitted',
            'Proposal Approved',
            'Requirement Analysis',
            'Design',
            'Development',
            'Testing',
            'Documentation',
            'Final Report',
            'Presentation',
            'Completed',
        ];

        foreach ($titles as $index => $title) {
            Milestone::query()->create([
                'project_id' => $project->id,
                'title' => $title,
                'due_date' => now()->addWeeks($index + 1)->toDateString(),
                'status' => $index === 0 ? MilestoneStatus::Completed : MilestoneStatus::Pending,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
