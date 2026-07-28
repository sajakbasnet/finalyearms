<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinalSubmission;
use App\Models\ProposalComment;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->student = User::query()->where('email', 'student@fyp.local')->firstOrFail();
        Sanctum::actingAs($this->student);
    }

    public function test_student_can_view_supervisor_overview(): void
    {
        $this->getJson('/api/student/overview')
            ->assertOk()
            ->assertJsonPath('data.supervisor.email', 'teacher@fyp.local')
            ->assertJsonPath('data.supervisor.name', 'Dr. Sarah Sharma')
            ->assertJsonPath('data.student.registration_number', 'CS-2022-001')
            ->assertJsonPath('data.project.title', 'Smart Campus Attendance System');
    }

    public function test_student_can_load_workspace_with_proposal_and_comments(): void
    {
        $this->getJson('/api/student/workspace')
            ->assertOk()
            ->assertJsonPath('data.latest_proposal.status', 'revision_requested')
            ->assertJsonStructure([
                'data' => [
                    'project',
                    'latest_proposal' => ['id', 'title', 'comments', 'can_edit'],
                    'versions',
                    'progress_reports',
                    'final_submission',
                ],
            ])
            ->assertJsonPath('data.latest_proposal.can_edit', true)
            ->assertJsonPath('data.latest_proposal.comments.0.section', 'methodology');
    }

    public function test_student_can_save_revision_draft_with_pdf(): void
    {
        Storage::fake('local');

        $this->post('/api/student/proposals', [
            'title' => 'Smart Campus Attendance System Updated',
            'abstract' => 'Updated abstract with privacy controls.',
            'methodology' => 'Added consent workflow.',
            'technologies' => 'Laravel, React, Python',
            'pdf' => UploadedFile::fake()->create('proposal.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.has_pdf', true);

        $this->assertDatabaseHas('proposal_versions', [
            'version_number' => 2,
            'status' => 'draft',
            'title' => 'Smart Campus Attendance System Updated',
        ]);
    }

    public function test_student_can_update_existing_draft(): void
    {
        Storage::fake('local');

        $this->postJson('/api/student/proposals', [
            'title' => 'Draft One',
            'abstract' => 'First abstract',
        ])->assertOk()->assertJsonPath('data.version_number', 2);

        $this->postJson('/api/student/proposals', [
            'title' => 'Draft One Edited',
            'abstract' => 'Second abstract',
        ])
            ->assertOk()
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.title', 'Draft One Edited');

        $this->assertSame(2, ProposalVersion::query()->count());
    }

    public function test_student_can_submit_revision(): void
    {
        Storage::fake('local');

        $this->postJson('/api/student/proposals', [
            'title' => 'Revised Proposal',
            'abstract' => 'Privacy-aware attendance system.',
        ])->assertOk();

        $this->postJson('/api/student/proposals/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'resubmitted');

        $this->assertNotNull(
            $this->student->notifications()
                ->get()
                ->first(fn ($n) => ($n->data['type'] ?? null) === 'proposal_submitted')
        );
    }

    public function test_student_cannot_submit_without_abstract(): void
    {
        ProposalVersion::query()->latest('id')->firstOrFail()->update([
            'status' => 'draft',
            'abstract' => null,
            'title' => 'Incomplete',
        ]);

        $this->postJson('/api/student/proposals/submit')->assertUnprocessable();
    }

    public function test_student_can_reply_to_teacher_comment(): void
    {
        $comment = ProposalComment::query()->firstOrFail();

        $this->postJson("/api/student/comments/{$comment->id}/replies", [
            'reply' => 'We will add a consent checkbox and encrypted storage.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reply', 'We will add a consent checkbox and encrypted storage.');

        $this->assertDatabaseHas('proposal_replies', [
            'proposal_comment_id' => $comment->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_student_reply_requires_content(): void
    {
        $comment = ProposalComment::query()->firstOrFail();

        $this->postJson("/api/student/comments/{$comment->id}/replies", [
            'reply' => '',
        ])->assertUnprocessable();
    }

    public function test_student_can_submit_progress_report(): void
    {
        $this->postJson('/api/student/progress-reports', [
            'title' => 'Week 2 update',
            'description' => 'Finished dataset cleanup.',
            'percentage_completed' => 40,
            'demo_link' => 'https://example.com/demo',
            'git_repository' => 'https://github.com/example/fyp',
        ])
            ->assertCreated()
            ->assertJsonPath('data.percentage_completed', 40)
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('progress_reports', [
            'title' => 'Week 2 update',
            'percentage_completed' => 40,
            'submitted_by' => $this->student->id,
        ]);
    }

    public function test_progress_report_validates_percentage_range(): void
    {
        $this->postJson('/api/student/progress-reports', [
            'title' => 'Invalid',
            'percentage_completed' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors(['percentage_completed']);
    }

    public function test_student_can_view_timeline_milestones(): void
    {
        $this->getJson('/api/student/timeline')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'status_label', 'due_date']]])
            ->assertJsonPath('data.0.title', 'Proposal Submitted');
    }

    public function test_student_can_upload_final_report_and_presentation(): void
    {
        Storage::fake('local');

        $this->post('/api/student/final-submission', [
            'thesis' => UploadedFile::fake()->create('thesis.pdf', 200, 'application/pdf'),
            'presentation' => UploadedFile::fake()->create('slides.pdf', 200, 'application/pdf'),
            'github_repository' => 'https://github.com/example/fyp',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_thesis', true)
            ->assertJsonPath('data.has_presentation', true)
            ->assertJsonPath('data.github_repository', 'https://github.com/example/fyp');

        $this->assertSame(1, FinalSubmission::query()->count());
    }

    public function test_final_upload_requires_at_least_one_file_or_github(): void
    {
        $this->postJson('/api/student/final-submission', [])
            ->assertUnprocessable();
    }

    public function test_student_can_list_and_mark_notifications(): void
    {
        $list = $this->getJson('/api/student/notifications')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'data', 'read_at', 'created_at']]]);

        $notificationId = $list->json('data.0.id');
        $this->assertNotEmpty($notificationId);

        $this->postJson("/api/student/notifications/{$notificationId}/read")
            ->assertOk();

        $this->assertNotNull(
            $this->student->notifications()->where('id', $notificationId)->first()?->read_at
        );
    }

    public function test_student_can_mark_all_notifications_read(): void
    {
        $this->assertTrue($this->student->unreadNotifications()->exists());

        $this->postJson('/api/student/notifications/read-all')->assertOk();

        $this->assertFalse($this->student->fresh()->unreadNotifications()->exists());
    }

    public function test_student_cannot_edit_approved_proposal(): void
    {
        ProposalVersion::query()->latest('id')->firstOrFail()->update(['status' => 'approved']);

        $this->postJson('/api/student/proposals', [
            'title' => 'Should fail',
            'abstract' => 'Nope',
        ])->assertUnprocessable();
    }

    public function test_student_cannot_edit_submitted_proposal(): void
    {
        ProposalVersion::query()->latest('id')->firstOrFail()->update(['status' => 'submitted']);

        $this->postJson('/api/student/proposals', [
            'title' => 'Should fail',
            'abstract' => 'Nope',
        ])->assertUnprocessable();
    }

    public function test_unassigned_student_cannot_create_proposal(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'student2@fyp.local')->firstOrFail());

        $this->postJson('/api/student/proposals', [
            'title' => 'No supervisor project',
            'abstract' => 'Should fail',
        ])->assertUnprocessable();
    }

    public function test_proposal_save_requires_title(): void
    {
        $this->postJson('/api/student/proposals', [
            'abstract' => 'Missing title',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title']);
    }
}
