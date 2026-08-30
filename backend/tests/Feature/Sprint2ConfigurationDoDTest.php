<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityTemplate;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sprint 2 Definition of Done, end to end:
 *
 *   "Coordinator can configure the academic environment and publish a usable
 *    activity template."
 *
 * Walks the whole configuration path over HTTP with the two roles that own it —
 * the Institution Admin sets up structure, the Coordinator builds and publishes
 * the template — and asserts the split between them holds throughout.
 */
final class Sprint2ConfigurationDoDTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
    }

    private function asCoordinator(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());
    }

    public function test_the_academic_environment_can_be_configured_and_a_template_published(): void
    {
        // ---- Institution Admin: departments, calendar, batches -----------
        $this->asAdmin();

        $departmentId = $this->postJson('/api/admin/departments', [
            'name' => 'Electronics Engineering',
            'code' => 'EEE',
        ])->assertCreated()->json('data.id');

        $sessionId = $this->postJson('/api/admin/sessions', [
            'name' => '2027-2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/admin/sessions/{$sessionId}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        foreach ([['Proposal deadline', '2027-10-15', true], ['Defence week', '2028-05-20', true]] as [$label, $date, $deadline]) {
            $this->postJson("/api/admin/sessions/{$sessionId}/dates", [
                'label' => $label, 'date' => $date, 'is_deadline' => $deadline,
            ])->assertCreated();
        }

        $batchId = $this->postJson('/api/admin/batches', [
            'department_id' => $departmentId,
            'name' => '2027 Intake',
            'intake_year' => 2027,
        ])->assertCreated()->json('data.id');

        // A student can be enrolled into the environment just configured.
        $this->postJson('/api/admin/students', [
            'name' => 'Sita Rai',
            'email' => 'sita@fyp.local',
            'password' => 'a-secure-password',
            'department_id' => $departmentId,
            'academic_session_id' => $sessionId,
            'batch_id' => $batchId,
            'registration_number' => 'EEE-2027-001',
        ])->assertCreated()->assertJsonPath('data.batch', '2027 Intake');

        // ---- Coordinator: project type and template ----------------------
        $this->asCoordinator();

        $projectTypeId = $this->postJson('/api/coordinator/project-types', [
            'name' => 'Hardware Build',
            'description' => 'Embedded systems capstone.',
        ])->assertCreated()->json('data.id');

        // Every item type, in a sequential template.
        $templateId = $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Hardware Build — standard timeline',
            'project_type_id' => $projectTypeId,
            'is_sequential' => true,
            'items' => [
                ['type' => 'task', 'title' => 'Proposal submission', 'due_offset_days' => 14],
                ['type' => 'approval_gate', 'title' => 'Proposal approved', 'due_offset_days' => 21,
                    'config' => ['approver_role' => 'supervisor']],
                ['type' => 'recurring_log', 'title' => 'Weekly build log', 'due_offset_days' => 28,
                    'config' => ['cadence' => 'weekly', 'occurrences' => 8]],
                ['type' => 'meeting_milestone', 'title' => 'Design review', 'due_offset_days' => 100,
                    'config' => ['minimum_meetings' => 1, 'duration_minutes' => 60]],
                ['type' => 'task', 'title' => 'Final demonstration', 'due_offset_days' => 150],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_usable', false)
            ->assertJsonCount(5, 'data.items')
            ->json('data.id');

        // ---- Publish: the Definition of Done ------------------------------
        $this->postJson("/api/coordinator/activity-templates/{$templateId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_usable', true)
            ->assertJsonPath('data.version', 1);

        $template = ActivityTemplate::query()->with('items')->findOrFail($templateId);
        $this->assertTrue($template->isUsable());

        // The recurring log runs past its start, so the plan spans further than
        // its last item's own offset.
        $this->assertSame(150, $template->spanDays());
        $this->assertSame(77, $template->items->firstWhere('title', 'Weekly build log')?->endOffsetDays());
    }

    public function test_neither_role_can_do_the_other_half(): void
    {
        $department = Department::query()->firstOrFail();

        // Coordinators configure the academic cycle, not the institution.
        $this->asCoordinator();
        $this->postJson('/api/admin/batches', [
            'department_id' => $department->id, 'name' => 'X', 'intake_year' => 2027,
        ])->assertForbidden();
        $this->postJson('/api/admin/departments', ['name' => 'X', 'code' => 'X'])->assertForbidden();

        // Institution Admins run the institution, not the templates.
        $this->asAdmin();
        $this->postJson('/api/coordinator/project-types', ['name' => 'X'])->assertForbidden();
        $this->getJson('/api/coordinator/activity-templates')->assertForbidden();
    }

    /**
     * A published template is the unit a project adopts, so it must not be able
     * to change underneath one. Revising it produces a new version instead.
     */
    public function test_a_published_template_is_revised_by_versioning_not_editing(): void
    {
        $this->asCoordinator();

        $published = ActivityTemplate::query()->where('is_default', true)->firstOrFail();
        $originalItemCount = $published->items()->count();

        $this->putJson("/api/coordinator/activity-templates/{$published->id}", ['name' => 'Edited'])
            ->assertUnprocessable();

        $draftId = $this->postJson("/api/coordinator/activity-templates/{$published->id}/versions")
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->json('data.id');

        $this->putJson("/api/coordinator/activity-templates/{$draftId}", [
            'name' => 'Revised timeline',
            'items' => [['type' => 'task', 'title' => 'Single milestone', 'due_offset_days' => 30]],
        ])->assertOk();

        // v1 is untouched and still the usable one until v2 is published.
        $published->refresh();
        $this->assertSame(1, $published->version);
        $this->assertSame($originalItemCount, $published->items()->count());
        $this->assertTrue($published->isUsable());

        $this->assertFalse(ActivityTemplate::query()->findOrFail($draftId)->isUsable());
    }
}
