<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Models\ActivityTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Typed items, cadence, sequencing, publishing, versioning and cloning.
 */
final class ActivityTemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function draft(array $items = [], bool $sequential = false, string $name = 'Draft'): ActivityTemplate
    {
        $id = $this->postJson('/api/coordinator/activity-templates', [
            'name' => $name,
            'is_sequential' => $sequential,
            'items' => $items ?: [
                ['type' => 'task', 'title' => 'Only task', 'due_offset_days' => 10],
            ],
        ])->assertCreated()->json('data.id');

        return ActivityTemplate::query()->findOrFail($id);
    }

    // ---------------------------------------------------------------
    // Item types
    // ---------------------------------------------------------------

    public function test_the_builder_can_discover_item_types_and_cadences(): void
    {
        $response = $this->getJson('/api/coordinator/activity-templates/item-types')->assertOk();

        $this->assertSame(
            ['task', 'approval_gate', 'meeting_milestone', 'recurring_log'],
            array_column($response->json('data.item_types'), 'value'),
        );
        $this->assertSame(
            ['weekly', 'fortnightly', 'monthly'],
            array_column($response->json('data.cadences'), 'value'),
        );
    }

    public function test_all_four_item_types_can_be_built(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Everything',
            'items' => [
                ['type' => 'task', 'title' => 'Submit proposal', 'due_offset_days' => 7],
                ['type' => 'approval_gate', 'title' => 'Proposal approved', 'due_offset_days' => 14,
                    'config' => ['approver_role' => 'supervisor']],
                ['type' => 'meeting_milestone', 'title' => 'Design review', 'due_offset_days' => 30,
                    'config' => ['minimum_meetings' => 2, 'duration_minutes' => 45]],
                ['type' => 'recurring_log', 'title' => 'Weekly log', 'due_offset_days' => 35,
                    'config' => ['cadence' => 'weekly', 'occurrences' => 10]],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(4, 'data.items')
            ->assertJsonPath('data.items.1.type_label', 'Approval Gate')
            ->assertJsonPath('data.items.3.config.cadence', 'weekly');
    }

    /** Approval gates exist to halt work, so they block unless told otherwise. */
    public function test_an_approval_gate_blocks_progression_by_default(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Gated',
            'items' => [
                ['type' => 'approval_gate', 'title' => 'Sign-off', 'due_offset_days' => 14,
                    'config' => ['approver_role' => 'supervisor']],
                ['type' => 'task', 'title' => 'After', 'due_offset_days' => 20],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.blocks_progression', true)
            ->assertJsonPath('data.items.1.blocks_progression', false);
    }

    // ---------------------------------------------------------------
    // Per-type config validation
    // ---------------------------------------------------------------

    public function test_a_recurring_log_requires_a_cadence_and_occurrences(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Bad log',
            'items' => [['type' => 'recurring_log', 'title' => 'Log', 'due_offset_days' => 7]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.config.cadence', 'items.0.config.occurrences']);
    }

    public function test_an_unknown_cadence_is_rejected(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Bad cadence',
            'items' => [['type' => 'recurring_log', 'title' => 'Log', 'due_offset_days' => 7,
                'config' => ['cadence' => 'hourly', 'occurrences' => 5]]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.config.cadence');
    }

    /** A student approving their own gate would defeat the point of one. */
    public function test_a_student_cannot_be_an_approver(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Self-approving',
            'items' => [['type' => 'approval_gate', 'title' => 'Gate', 'due_offset_days' => 7,
                'config' => ['approver_role' => 'student']]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.config.approver_role');
    }

    /**
     * A misspelled setting looks applied but does nothing, so it is rejected
     * rather than quietly dropped.
     */
    public function test_an_unrecognised_config_key_is_rejected(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Typo',
            'items' => [['type' => 'recurring_log', 'title' => 'Log', 'due_offset_days' => 7,
                'config' => ['cadence' => 'weekly', 'occurrences' => 5, 'freqency' => 'weekly']]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.config.freqency');
    }

    public function test_a_recurring_log_cannot_repeat_unreasonably_often(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Too many',
            'items' => [['type' => 'recurring_log', 'title' => 'Log', 'due_offset_days' => 7,
                'config' => ['cadence' => 'weekly', 'occurrences' => 500]]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.config.occurrences');
    }

    /** A recurring log runs past its start, so the template spans further. */
    public function test_cadence_extends_the_end_offset_of_a_recurring_log(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Spanning',
            'items' => [['type' => 'recurring_log', 'title' => 'Weekly log', 'due_offset_days' => 10,
                'config' => ['cadence' => 'weekly', 'occurrences' => 5]]],
        ])
            ->assertCreated()
            // 10 + 7 * (5 - 1)
            ->assertJsonPath('data.items.0.end_offset_days', 38)
            ->assertJsonPath('data.span_days', 38);
    }

    // ---------------------------------------------------------------
    // Publishing
    // ---------------------------------------------------------------

    public function test_publishing_makes_a_draft_usable(): void
    {
        $template = $this->draft();

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_usable', true)
            ->assertJsonPath('data.is_editable', false);

        $this->assertNotNull($template->fresh()->published_at);
    }

    public function test_an_empty_template_cannot_be_published(): void
    {
        $id = $this->postJson('/api/coordinator/activity-templates', ['name' => 'Empty'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/coordinator/activity-templates/{$id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    /**
     * The whole point of immutability: adopting a template must give a project
     * a plan that cannot change underneath it.
     */
    public function test_a_published_template_cannot_be_edited(): void
    {
        $template = $this->draft();
        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")->assertOk();

        $this->putJson("/api/coordinator/activity-templates/{$template->id}", ['name' => 'Changed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('Draft', $template->fresh()->name);
    }

    public function test_publishing_twice_is_refused(): void
    {
        $template = $this->draft();
        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")->assertOk();

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // Sequential enforcement
    // ---------------------------------------------------------------

    public function test_a_sequential_template_rejects_offsets_that_run_backwards(): void
    {
        $template = $this->draft([
            ['type' => 'task', 'title' => 'First', 'due_offset_days' => 30],
            ['type' => 'task', 'title' => 'Second', 'due_offset_days' => 10],
        ], sequential: true);

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_sequential');
    }

    public function test_a_sequential_template_requires_every_item_to_have_an_offset(): void
    {
        $template = $this->draft([
            ['type' => 'task', 'title' => 'First', 'due_offset_days' => 10],
            ['type' => 'task', 'title' => 'Undated'],
        ], sequential: true);

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_sequential');
    }

    /** A recurring log must finish before the next item is due. */
    public function test_a_recurring_log_cannot_overrun_the_item_after_it(): void
    {
        $template = $this->draft([
            ['type' => 'recurring_log', 'title' => 'Weekly log', 'due_offset_days' => 10,
                'config' => ['cadence' => 'weekly', 'occurrences' => 10]],
            ['type' => 'task', 'title' => 'Too soon', 'due_offset_days' => 20],
        ], sequential: true);

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_sequential');
    }

    /** A gate at the end blocks nothing, which is always a mistake. */
    public function test_a_trailing_blocking_gate_is_rejected(): void
    {
        $template = $this->draft([
            ['type' => 'task', 'title' => 'Work', 'due_offset_days' => 10],
            ['type' => 'approval_gate', 'title' => 'Final sign-off', 'due_offset_days' => 20,
                'config' => ['approver_role' => 'supervisor']],
        ], sequential: true);

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_a_coherent_sequential_template_publishes(): void
    {
        $template = $this->draft([
            ['type' => 'task', 'title' => 'Proposal', 'due_offset_days' => 7],
            ['type' => 'approval_gate', 'title' => 'Approved', 'due_offset_days' => 14,
                'config' => ['approver_role' => 'supervisor']],
            ['type' => 'recurring_log', 'title' => 'Weekly log', 'due_offset_days' => 21,
                'config' => ['cadence' => 'weekly', 'occurrences' => 4]],
            ['type' => 'task', 'title' => 'Final report', 'due_offset_days' => 60],
        ], sequential: true);

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    // ---------------------------------------------------------------
    // Versioning
    // ---------------------------------------------------------------

    public function test_versioning_forks_a_draft_and_leaves_the_published_one_alone(): void
    {
        $v1 = $this->draft();
        $this->postJson("/api/coordinator/activity-templates/{$v1->id}/publish")->assertOk();

        $response = $this->postJson("/api/coordinator/activity-templates/{$v1->id}/versions")
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.parent_id', $v1->id);

        // The published version is untouched and still usable.
        $v1 = $v1->fresh();
        $this->assertSame(TemplateStatus::Published, $v1->status);
        $this->assertSame(1, $v1->version);

        // Items came across with the fork.
        $this->assertSame(
            $v1->items()->count(),
            ActivityTemplate::query()->findOrFail($response->json('data.id'))->items()->count(),
        );
    }

    public function test_a_draft_cannot_be_versioned(): void
    {
        $template = $this->draft();

        $this->postJson("/api/coordinator/activity-templates/{$template->id}/versions")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** Two open drafts of one version would diverge silently. */
    public function test_only_one_open_draft_per_published_version(): void
    {
        $v1 = $this->draft();
        $this->postJson("/api/coordinator/activity-templates/{$v1->id}/publish")->assertOk();

        $this->postJson("/api/coordinator/activity-templates/{$v1->id}/versions")->assertCreated();

        $this->postJson("/api/coordinator/activity-templates/{$v1->id}/versions")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_versions_keep_climbing(): void
    {
        $v1 = $this->draft();
        $this->postJson("/api/coordinator/activity-templates/{$v1->id}/publish")->assertOk();

        $v2Id = $this->postJson("/api/coordinator/activity-templates/{$v1->id}/versions")
            ->assertCreated()->json('data.id');
        $this->postJson("/api/coordinator/activity-templates/{$v2Id}/publish")->assertOk();

        $this->postJson("/api/coordinator/activity-templates/{$v2Id}/versions")
            ->assertCreated()
            ->assertJsonPath('data.version', 3);
    }

    // ---------------------------------------------------------------
    // Cloning
    // ---------------------------------------------------------------

    public function test_cloning_copies_items_into_a_new_draft_at_version_one(): void
    {
        $source = ActivityTemplate::query()->where('is_default', true)->firstOrFail();

        $response = $this->postJson("/api/coordinator/activity-templates/{$source->id}/clone", [
            'name' => 'Our own timeline',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Our own timeline')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.parent_id', $source->id);

        $this->assertCount($source->items()->count(), $response->json('data.items'));
    }

    /** Cloning a published default is how an institution starts from one. */
    public function test_a_clone_of_a_published_template_is_editable(): void
    {
        $source = ActivityTemplate::query()->where('is_default', true)->firstOrFail();

        $cloneId = $this->postJson("/api/coordinator/activity-templates/{$source->id}/clone", [
            'name' => 'Editable copy',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/coordinator/activity-templates/{$cloneId}", [
            'name' => 'Renamed copy',
        ])->assertOk()->assertJsonPath('data.name', 'Renamed copy');
    }

    public function test_a_clone_needs_a_name(): void
    {
        $source = ActivityTemplate::query()->where('is_default', true)->firstOrFail();

        $this->postJson("/api/coordinator/activity-templates/{$source->id}/clone", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    // ---------------------------------------------------------------
    // Archiving and authorization
    // ---------------------------------------------------------------

    public function test_deleting_archives_rather_than_removes(): void
    {
        $template = $this->draft();

        $this->deleteJson("/api/coordinator/activity-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('activity_templates', [
            'id' => $template->id,
            'status' => 'archived',
        ]);
    }

    public function test_an_archived_template_cannot_be_edited(): void
    {
        $template = $this->draft();
        $this->deleteJson("/api/coordinator/activity-templates/{$template->id}")->assertOk();

        $this->putJson("/api/coordinator/activity-templates/{$template->id}", ['name' => 'Back'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_templates_can_be_filtered_by_status(): void
    {
        $this->draft();

        $this->getJson('/api/coordinator/activity-templates?status=published')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/coordinator/activity-templates?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_institution_admin_cannot_build_or_publish_templates(): void
    {
        $template = $this->draft();
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());

        $this->getJson('/api/coordinator/activity-templates')->assertForbidden();
        $this->postJson("/api/coordinator/activity-templates/{$template->id}/publish")->assertForbidden();
        $this->postJson("/api/coordinator/activity-templates/{$template->id}/clone", ['name' => 'X'])
            ->assertForbidden();
    }
}
