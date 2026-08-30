<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReviewProgressRequest;
use App\Http\Requests\Teacher\ReviewProposalRequest;
use App\Http\Requests\Teacher\StoreEvaluationRequest;
use App\Http\Requests\Teacher\StoreProposalCommentRequest;
use App\Http\Requests\Teacher\StoreProposalReplyRequest;
use App\Http\Requests\Teacher\UpdateMilestoneRequest;
use App\Models\Milestone;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\ProposalComment;
use App\Models\ProposalVersion;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherService $teacherService,
    ) {}

    public function students(Request $request): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $assignments = $this->teacherService->assignedStudents($teacher);

        return response()->json([
            'data' => $assignments->map(function ($assignment) {
                $student = $assignment->student;
                $project = $student?->projects->first();

                return [
                    'assignment_id' => $assignment->id,
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                    'student' => [
                        'id' => $student?->id,
                        'name' => $student?->user?->name,
                        'email' => $student?->user?->email,
                        'registration_number' => $student?->registration_number,
                        'batch' => $student?->batch?->name,
                        'department' => $student?->department?->name,
                        'session' => $student?->academicSession?->name,
                    ],
                    'project' => $project ? [
                        'id' => $project->id,
                        'title' => $project->title,
                        'status' => $project->status?->value,
                        'status_label' => $project->status?->label(),
                    ] : null,
                ];
            }),
        ]);
    }

    public function proposals(Request $request): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $proposals = $this->teacherService->listProposals(
            $teacher,
            $request->string('status')->toString() ?: null,
        );

        return response()->json([
            'data' => $proposals->map(fn (ProposalVersion $proposal) => $this->mapProposalSummary($proposal)),
        ]);
    }

    public function showProposal(Request $request, ProposalVersion $proposal): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $proposal = $this->teacherService->getProposal($teacher, $proposal);

        return response()->json([
            'data' => $this->mapProposalDetail($proposal),
        ]);
    }

    public function reviewProposal(ReviewProposalRequest $request, ProposalVersion $proposal): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $proposal = $this->teacherService->reviewProposal(
            teacher: $teacher,
            proposal: $proposal,
            actor: $request->user(),
            action: $request->string('action')->toString(),
            comment: $request->string('comment')->toString() ?: null,
            section: $request->string('section')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Proposal review saved.',
            'data' => $this->mapProposalDetail($proposal),
        ]);
    }

    public function commentOnProposal(StoreProposalCommentRequest $request, ProposalVersion $proposal): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $comment = $this->teacherService->addComment(
            teacher: $teacher,
            proposal: $proposal,
            actor: $request->user(),
            comment: $request->string('comment')->toString(),
            section: $request->string('section')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Comment added.',
            'data' => $this->mapComment($comment),
        ], 201);
    }

    public function replyToComment(StoreProposalReplyRequest $request, ProposalComment $comment): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $reply = $this->teacherService->replyToComment(
            teacher: $teacher,
            comment: $comment,
            actor: $request->user(),
            reply: $request->string('reply')->toString(),
        );

        return response()->json([
            'message' => 'Reply added.',
            'data' => [
                'id' => $reply->id,
                'reply' => $reply->reply,
                'user' => [
                    'id' => $reply->user?->id,
                    'name' => $reply->user?->name,
                ],
                'created_at' => $reply->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function progressReports(Request $request): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $reports = $this->teacherService->listProgressReports($teacher);

        return response()->json([
            'data' => $reports->map(fn (ProgressReport $report) => $this->mapProgressReport($report)),
        ]);
    }

    public function reviewProgress(ReviewProgressRequest $request, ProgressReport $progressReport): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $report = $this->teacherService->reviewProgressReport(
            teacher: $teacher,
            report: $progressReport,
            actor: $request->user(),
            action: $request->string('action')->toString(),
            comment: $request->string('comment')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Progress report reviewed.',
            'data' => $this->mapProgressReport($report),
        ]);
    }

    public function showProject(Request $request, Project $project): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $project = $this->teacherService->getProjectWorkspace($teacher, $project);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'status' => $project->status?->value,
                'status_label' => $project->status?->label(),
                'student' => [
                    'id' => $project->student?->id,
                    'name' => $project->student?->user?->name,
                    'registration_number' => $project->student?->registration_number,
                    'department' => $project->student?->department?->name,
                ],
                'milestones' => $project->milestones->map(fn ($m) => $this->mapMilestone($m)),
                'progress_reports' => $project->progressReports->map(fn ($r) => $this->mapProgressReport($r)),
                'evaluation' => $project->evaluation ? $this->mapEvaluation($project->evaluation) : null,
                'files' => $this->teacherService->listDownloadableFiles($teacher, $project),
                'final_submission' => $project->finalSubmission ? [
                    'github_repository' => $project->finalSubmission->github_repository,
                    'submitted_at' => $project->finalSubmission->submitted_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function updateMilestone(UpdateMilestoneRequest $request, Milestone $milestone): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $milestone = $this->teacherService->updateMilestone(
            teacher: $teacher,
            milestone: $milestone,
            status: $request->string('status')->toString(),
            remarks: $request->string('remarks')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Milestone updated.',
            'data' => $this->mapMilestone($milestone),
        ]);
    }

    public function saveEvaluation(StoreEvaluationRequest $request, Project $project): JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $evaluation = $this->teacherService->saveEvaluation($teacher, $project, $request->validated());

        return response()->json([
            'message' => 'Evaluation saved.',
            'data' => $this->mapEvaluation($evaluation),
        ]);
    }

    public function downloadFile(Request $request, Project $project): StreamedResponse|JsonResponse
    {
        $teacher = $this->teacherService->resolveTeacher($request->user());
        $path = $request->string('path')->toString();

        if ($path === '') {
            return response()->json(['message' => 'File path is required.'], 422);
        }

        return $this->teacherService->downloadFile($teacher, $project, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProposalSummary(ProposalVersion $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'version_number' => $proposal->version_number,
            'status' => $proposal->status?->value,
            'status_label' => $proposal->status?->label(),
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'project_id' => $proposal->project_id,
            'student' => [
                'name' => $proposal->project?->student?->user?->name,
                'registration_number' => $proposal->project?->student?->registration_number,
                'department' => $proposal->project?->student?->department?->name,
            ],
            'has_pdf' => filled($proposal->pdf_path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProposalDetail(ProposalVersion $proposal): array
    {
        return [
            ...$this->mapProposalSummary($proposal),
            'abstract' => $proposal->abstract,
            'background' => $proposal->background,
            'problem_statement' => $proposal->problem_statement,
            'objectives' => $proposal->objectives,
            'scope' => $proposal->scope,
            'methodology' => $proposal->methodology,
            'literature_review' => $proposal->literature_review,
            'timeline' => $proposal->timeline,
            'expected_outcome' => $proposal->expected_outcome,
            'technologies' => $proposal->technologies,
            'references' => $proposal->references,
            'pdf_path' => $proposal->pdf_path,
            'comments' => $proposal->comments->map(fn ($comment) => $this->mapComment($comment)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapComment(ProposalComment $comment): array
    {
        return [
            'id' => $comment->id,
            'section' => $comment->section,
            'comment' => $comment->comment,
            'action' => $comment->action,
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name,
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
            'replies' => $comment->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'reply' => $reply->reply,
                'user' => [
                    'id' => $reply->user?->id,
                    'name' => $reply->user?->name,
                ],
                'created_at' => $reply->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProgressReport(ProgressReport $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'description' => $report->description,
            'percentage_completed' => $report->percentage_completed,
            'demo_link' => $report->demo_link,
            'git_repository' => $report->git_repository,
            'status' => $report->status,
            'submitted_at' => $report->submitted_at?->toIso8601String(),
            'project' => [
                'id' => $report->project?->id,
                'title' => $report->project?->title,
            ],
            'student' => [
                'name' => $report->project?->student?->user?->name,
            ],
            'comments' => $report->relationLoaded('comments')
                ? $report->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'comment' => $c->comment,
                    'action' => $c->action,
                    'user' => $c->user?->name,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMilestone(Milestone $milestone): array
    {
        return [
            'id' => $milestone->id,
            'title' => $milestone->title,
            'description' => $milestone->description,
            'due_date' => $milestone->due_date?->toDateString(),
            'status' => $milestone->status?->value,
            'status_label' => $milestone->status?->label(),
            'remarks' => $milestone->remarks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEvaluation(mixed $evaluation): array
    {
        return [
            'id' => $evaluation->id,
            'innovation' => $evaluation->innovation,
            'implementation' => $evaluation->implementation,
            'documentation' => $evaluation->documentation,
            'presentation' => $evaluation->presentation,
            'testing' => $evaluation->testing,
            'overall_score' => $evaluation->overall_score,
            'remarks' => $evaluation->remarks,
        ];
    }
}
