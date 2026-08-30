<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivityItemType;
use App\Enums\RecurringCadence;
use App\Enums\TemplateStatus;
use App\Models\ActivityTemplate;
use App\Models\ProjectType;
use Illuminate\Database\Seeder;

/**
 * The project types and activity templates every new tenant starts with.
 *
 * Idempotent and non-destructive: rows are matched on slug/name and only the
 * seeded defaults are touched, so re-running after a coordinator has edited
 * their own copies will not overwrite local changes.
 *
 * Defaults ship **published**, so a freshly provisioned tenant has a usable
 * template on day one without a coordinator having to publish one first.
 */
final class DefaultTemplatesSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, description: string, requires_employer?: bool}> */
    private const PROJECT_TYPES = [
        [
            'name' => 'Research',
            'slug' => 'research',
            'description' => 'Investigation-led project producing a thesis and findings.',
        ],
        [
            'name' => 'Development',
            'slug' => 'development',
            'description' => 'Software or hardware build delivering a working artefact.',
        ],
        [
            'name' => 'Industry Internship',
            'slug' => 'industry-internship',
            'description' => 'Placement with a host organisation, evaluated jointly with an employer.',
            'requires_employer' => true,
        ],
        [
            'name' => 'Capstone',
            'slug' => 'capstone',
            'description' => 'Team capstone spanning the full academic session.',
        ],
    ];

    /**
     * Default timelines, exercising every item type.
     *
     * @var array<string, list<array{title: string, type: string, due_offset_days: int, config?: array<string, mixed>, blocks?: bool}>>
     */
    private const TEMPLATE_ITEMS = [
        'development' => [
            ['title' => 'Proposal submission', 'type' => 'task', 'due_offset_days' => 14],
            ['title' => 'Proposal approved', 'type' => 'approval_gate', 'due_offset_days' => 21,
                'config' => ['approver_role' => 'supervisor'], 'blocks' => true],
            ['title' => 'Weekly supervision log', 'type' => 'recurring_log', 'due_offset_days' => 28,
                'config' => ['cadence' => RecurringCadence::Weekly->value, 'occurrences' => 20]],
            ['title' => 'Requirement analysis complete', 'type' => 'task', 'due_offset_days' => 35],
            ['title' => 'Design review meeting', 'type' => 'meeting_milestone', 'due_offset_days' => 56,
                'config' => ['minimum_meetings' => 1, 'duration_minutes' => 60]],
            ['title' => 'Mid-term demonstration', 'type' => 'task', 'due_offset_days' => 98],
            ['title' => 'Feature freeze', 'type' => 'task', 'due_offset_days' => 140],
            ['title' => 'Testing and documentation', 'type' => 'task', 'due_offset_days' => 168],
            ['title' => 'Final defence', 'type' => 'task', 'due_offset_days' => 189],
        ],
        'research' => [
            ['title' => 'Proposal submission', 'type' => 'task', 'due_offset_days' => 14],
            ['title' => 'Proposal approved', 'type' => 'approval_gate', 'due_offset_days' => 21,
                'config' => ['approver_role' => 'supervisor'], 'blocks' => true],
            ['title' => 'Fortnightly research log', 'type' => 'recurring_log', 'due_offset_days' => 28,
                'config' => ['cadence' => RecurringCadence::Fortnightly->value, 'occurrences' => 10]],
            ['title' => 'Literature review', 'type' => 'task', 'due_offset_days' => 42],
            ['title' => 'Methodology approval', 'type' => 'approval_gate', 'due_offset_days' => 70,
                'config' => ['approver_role' => 'supervisor'], 'blocks' => true],
            ['title' => 'Data collection complete', 'type' => 'task', 'due_offset_days' => 119],
            ['title' => 'Analysis and results', 'type' => 'task', 'due_offset_days' => 154],
            ['title' => 'Thesis draft', 'type' => 'task', 'due_offset_days' => 175],
            ['title' => 'Final defence', 'type' => 'task', 'due_offset_days' => 189],
        ],
        'industry-internship' => [
            ['title' => 'Placement confirmed', 'type' => 'task', 'due_offset_days' => 7],
            ['title' => 'Learning agreement signed', 'type' => 'approval_gate', 'due_offset_days' => 21,
                'config' => ['approver_role' => 'coordinator'], 'blocks' => true],
            ['title' => 'Monthly progress log', 'type' => 'recurring_log', 'due_offset_days' => 30,
                'config' => ['cadence' => RecurringCadence::Monthly->value, 'occurrences' => 5]],
            ['title' => 'Mid-placement employer review', 'type' => 'meeting_milestone', 'due_offset_days' => 84,
                'config' => ['minimum_meetings' => 1]],
            ['title' => 'Final employer evaluation', 'type' => 'task', 'due_offset_days' => 161],
            ['title' => 'Internship report submission', 'type' => 'task', 'due_offset_days' => 182],
        ],
    ];

    public function run(): void
    {
        foreach (self::PROJECT_TYPES as $index => $definition) {
            $type = ProjectType::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'requires_employer' => $definition['requires_employer'] ?? false,
                    'is_default' => true,
                    'sort_order' => $index,
                ],
            );

            $this->seedTemplateFor($type);
        }
    }

    private function seedTemplateFor(ProjectType $type): void
    {
        $items = self::TEMPLATE_ITEMS[$type->slug] ?? null;

        if ($items === null) {
            return;
        }

        $template = ActivityTemplate::query()->updateOrCreate(
            [
                'project_type_id' => $type->id,
                'name' => $type->name.' — standard timeline',
            ],
            [
                'description' => 'Default milestone schedule for '.$type->name.' projects.',
                'is_default' => true,
                'is_sequential' => true,
                'version' => 1,
                'status' => TemplateStatus::Published,
                'published_at' => now(),
            ],
        );

        // Rebuilt each run so an edited default returns to the shipped shape.
        // Coordinator-authored templates are untouched: they never carry these
        // matching keys.
        $template->items()->delete();

        foreach ($items as $order => $item) {
            $itemType = ActivityItemType::from($item['type']);

            $template->items()->create([
                'type' => $itemType,
                'title' => $item['title'],
                'due_offset_days' => $item['due_offset_days'],
                'config' => $item['config'] ?? null,
                'blocks_progression' => $item['blocks'] ?? $itemType->blocksByDefault(),
                'sort_order' => $order,
            ]);
        }
    }
}
