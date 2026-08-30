<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityTemplate;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CoordinatorTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());
    }

    /**
     * A fresh editable draft.
     *
     * Seeded defaults are published and therefore immutable, so anything
     * testing editing has to start from a draft of its own.
     */
    private function draftTemplate(): ActivityTemplate
    {
        $id = $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Working draft',
            'items' => [
                ['type' => 'task', 'title' => 'First', 'due_offset_days' => 5],
                ['type' => 'task', 'title' => 'Second', 'due_offset_days' => 10],
                ['type' => 'task', 'title' => 'Third', 'due_offset_days' => 15],
            ],
        ])->assertCreated()->json('data.id');

        return ActivityTemplate::query()->findOrFail($id);
    }

    public function test_defaults_are_listed(): void
    {
        $this->getJson('/api/coordinator/project-types')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.slug', 'research')
            ->assertJsonPath('data.0.is_default', true);
    }

    public function test_internship_type_is_flagged_as_requiring_an_employer(): void
    {
        $this->getJson('/api/coordinator/project-types')
            ->assertOk()
            ->assertJsonPath('data.2.slug', 'industry-internship')
            ->assertJsonPath('data.2.requires_employer', true);
    }

    public function test_a_coordinator_can_create_a_project_type(): void
    {
        $this->postJson('/api/coordinator/project-types', [
            'name' => 'Design Studio',
            'description' => 'Studio-based design work.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'design-studio')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('project_types', ['slug' => 'design-studio']);
    }

    public function test_slugs_must_be_unique(): void
    {
        $this->postJson('/api/coordinator/project-types', ['name' => 'Research'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    /**
     * Deleting a seeded default would only invite it back at the next
     * provisioning run, so it is deactivated instead.
     */
    public function test_deleting_a_default_type_deactivates_it(): void
    {
        $type = ProjectType::query()->where('slug', 'research')->firstOrFail();

        $this->deleteJson("/api/coordinator/project-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('project_types', ['id' => $type->id, 'is_active' => false]);
    }

    public function test_deleting_a_custom_type_removes_it(): void
    {
        $type = ProjectType::query()->create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $this->deleteJson("/api/coordinator/project-types/{$type->id}")->assertOk();

        $this->assertDatabaseMissing('project_types', ['id' => $type->id]);
    }

    public function test_an_activity_template_is_created_with_ordered_items(): void
    {
        $type = ProjectType::query()->where('slug', 'development')->firstOrFail();

        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Accelerated timeline',
            'project_type_id' => $type->id,
            'items' => [
                ['type' => 'task', 'title' => 'Kickoff', 'due_offset_days' => 3],
                ['type' => 'task', 'title' => 'Prototype', 'due_offset_days' => 30],
                ['type' => 'task', 'title' => 'Handover', 'due_offset_days' => 60],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Kickoff')
            ->assertJsonPath('data.items.0.sort_order', 0)
            ->assertJsonPath('data.items.2.sort_order', 2)
            ->assertJsonPath('data.project_type.slug', 'development');
    }

    /**
     * Items are replaced wholesale; a partial merge would strand rows the
     * editor has removed.
     */
    public function test_updating_items_replaces_rather_than_merges(): void
    {
        $template = $this->draftTemplate();
        $this->assertGreaterThan(2, $template->items()->count());

        $this->putJson("/api/coordinator/activity-templates/{$template->id}", [
            'items' => [
                ['type' => 'task', 'title' => 'Only milestone', 'due_offset_days' => 10],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Only milestone');

        $this->assertSame(1, $template->fresh()->items()->count());
    }

    public function test_omitting_items_leaves_them_untouched(): void
    {
        $template = $this->draftTemplate();
        $before = $template->items()->count();

        $this->putJson("/api/coordinator/activity-templates/{$template->id}", [
            'name' => 'Renamed template',
        ])->assertOk();

        $this->assertSame($before, $template->fresh()->items()->count());
    }

    public function test_item_offsets_must_be_within_range(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Bad offsets',
            'items' => [
                ['type' => 'task', 'title' => 'Way out', 'due_offset_days' => 5000],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.due_offset_days');
    }

    public function test_an_unknown_project_type_is_rejected(): void
    {
        $this->postJson('/api/coordinator/activity-templates', [
            'name' => 'Orphan',
            'project_type_id' => 9999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_type_id');
    }
}
