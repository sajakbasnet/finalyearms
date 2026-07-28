<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ReplyCommentRequest;
use App\Http\Requests\Student\SaveProposalRequest;
use App\Http\Requests\Student\StoreProgressReportRequest;
use App\Http\Requests\Student\UploadFinalRequest;
use App\Models\ProposalComment;
use App\Models\ProposalVersion;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $studentService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());

        return response()->json([
            'data' => $this->studentService->overview($student),
        ]);
    }

    public function workspace(Request $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $project = $this->studentService->getProjectWorkspace($student);

        if ($project === null) {
            return response()->json([
                'data' => null,
                'message' => 'No project yet. Start by creating a proposal.',
            ]);
        }

        $latest = $project->proposalVersions->sortByDesc('version_number')->first();

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'status' => $project->status?->value,
                    'status_label' => $project->status?->label(),
                    'supervisor' => $project->supervisor ? [
                        'name' => $project->supervisor->user?->name,
                        'email' => $project->supervisor->user?->email,
                    ] : null,
                ],
                'latest_proposal' => $latest ? $this->mapProposal($latest) : null,
                'versions' => $project->proposalVersions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn (ProposalVersion $version) => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'status' => $version->status?->value,
                        'status_label' => $version->status?->label(),
                        'submitted_at' => $version->submitted_at?->toIso8601String(),
                        'has_pdf' => filled($version->pdf_path),
                    ]),
                'progress_reports' => $project->progressReports->map(fn ($report) => [
                    'id' => $report->id,
                    'title' => $report->title,
                    'description' => $report->description,
                    'percentage_completed' => $report->percentage_completed,
                    'demo_link' => $report->demo_link,
                    'git_repository' => $report->git_repository,
                    'status' => $report->status,
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                    'comments' => $report->comments->map(fn ($c) => [
                        'id' => $c->id,
                        'comment' => $c->comment,
                        'action' => $c->action,
                        'user' => $c->user?->name,
                    ]),
                ]),
                'final_submission' => $project->finalSubmission ? [
                    'has_thesis' => filled($project->finalSubmission->thesis_path),
                    'has_presentation' => filled($project->finalSubmission->presentation_path),
                    'github_repository' => $project->finalSubmission->github_repository,
                    'submitted_at' => $project->finalSubmission->submitted_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function saveProposal(SaveProposalRequest $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $proposal = $this->studentService->saveProposalDraft(
            student: $student,
            actor: $request->user(),
            data: $request->safe()->except('pdf'),
            pdf: $request->file('pdf'),
        );

        return response()->json([
            'message' => 'Proposal draft saved.',
            'data' => $this->mapProposal($proposal),
        ]);
    }

    public function submitProposal(Request $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $proposal = $this->studentService->submitProposal(
            student: $student,
            actor: $request->user(),
            proposalId: $request->integer('proposal_id') ?: null,
        );

        return response()->json([
            'message' => 'Proposal submitted successfully.',
            'data' => $this->mapProposal($proposal),
        ]);
    }

    public function replyToComment(ReplyCommentRequest $request, ProposalComment $comment): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $reply = $this->studentService->replyToComment(
            student: $student,
            actor: $request->user(),
            comment: $comment,
            reply: $request->string('reply')->toString(),
        );

        return response()->json([
            'message' => 'Reply posted.',
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

    public function storeProgress(StoreProgressReportRequest $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $report = $this->studentService->submitProgressReport(
            student: $student,
            actor: $request->user(),
            data: $request->validated(),
        );

        return response()->json([
            'message' => 'Progress report submitted.',
            'data' => [
                'id' => $report->id,
                'title' => $report->title,
                'percentage_completed' => $report->percentage_completed,
                'status' => $report->status,
            ],
        ], 201);
    }

    public function uploadFinal(UploadFinalRequest $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());
        $submission = $this->studentService->uploadFinalFiles(
            student: $student,
            thesis: $request->file('thesis'),
            presentation: $request->file('presentation'),
            githubRepository: $request->string('github_repository')->toString() ?: null,
        );

        return response()->json([
            'message' => 'Final files uploaded.',
            'data' => [
                'has_thesis' => filled($submission->thesis_path),
                'has_presentation' => filled($submission->presentation_path),
                'github_repository' => $submission->github_repository,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
            ],
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $student = $this->studentService->resolveStudent($request->user());

        return response()->json([
            'data' => $this->studentService->timeline($student),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->studentService->notifications($request->user()),
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        $this->studentService->markNotificationRead($request->user(), $notification);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $this->studentService->markAllNotificationsRead($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProposal(ProposalVersion $proposal): array
    {
        $proposal->loadMissing(['comments.user', 'comments.replies.user']);

        return [
            'id' => $proposal->id,
            'project_id' => $proposal->project_id,
            'version_number' => $proposal->version_number,
            'title' => $proposal->title,
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
            'has_pdf' => filled($proposal->pdf_path),
            'status' => $proposal->status?->value,
            'status_label' => $proposal->status?->label(),
            'submitted_at' => $proposal->submitted_at?->toIso8601String(),
            'can_edit' => in_array($proposal->status?->value, ['draft', 'revision_requested'], true),
            'comments' => $proposal->comments->map(fn ($comment) => [
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
            ]),
        ];
    }
}
