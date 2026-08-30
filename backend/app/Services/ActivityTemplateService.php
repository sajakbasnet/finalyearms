<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityItemType;
use App\Enums\TemplateStatus;
use App\Models\ActivityTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Building, versioning and publishing activity templates.
 *
 * The rule the whole service is built around: **a published template is
 * immutable**. Editing one forks a new draft at the next version rather than
 * mutating in place, so adopting a template gives a project a plan that cannot
 * change underneath it.
 */
final class ActivityTemplateService
{
    private const MAX_ITEMS = 100;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ActivityTemplate
    {
        return DB::transaction(function () use ($data): ActivityTemplate {
            $template = ActivityTemplate::query()->create([
                'project_type_id' => $data['project_type_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_sequential' => $data['is_sequential'] ?? false,
                'status' => TemplateStatus::Draft,
                'version' => 1,
            ]);

            $this->replaceItems($template, $data['items'] ?? []);

            return $template->load('items');
        });
    }

    /**
     * Updates a draft in place.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the template is not a draft
     */
    public function update(ActivityTemplate $template, array $data): ActivityTemplate
    {
        $this->assertEditable($template);

        return DB::transaction(function () use ($template, $data): ActivityTemplate {
            $template->update(array_intersect_key($data, array_flip([
                'project_type_id', 'name', 'description', 'is_sequential', 'is_active',
            ])));

            if (array_key_exists('items', $data)) {
                $this->replaceItems($template, $data['items']);
            }

            return $template->fresh(['items', 'projectType']);
        });
    }

    /**
     * Publishes a draft, making it usable and immutable.
     *
     * Validates coherence first: an incoherent template is far more expensive
     * to discover once projects have adopted it.
     *
     * @throws ValidationException
     */
    public function publish(ActivityTemplate $template): ActivityTemplate
    {
        $this->assertEditable($template);

        $template->load('items');
        $this->assertCoherent($template);

        $template->update([
            'status' => TemplateStatus::Published,
            'published_at' => now(),
        ]);

        return $template->fresh(['items', 'projectType']);
    }

    /**
     * Opens a new draft version of a published template.
     *
     * The published version is left untouched and keeps serving anything that
     * adopted it; the draft carries the next version number in the same line.
     *
     * @throws ValidationException
     */
    public function newVersion(ActivityTemplate $template): ActivityTemplate
    {
        if ($template->status !== TemplateStatus::Published) {
            throw ValidationException::withMessages([
                'status' => 'Only a published template can be versioned. This one is a '
                    .$template->status->value.'; edit it directly instead.',
            ]);
        }

        $existingDraft = ActivityTemplate::query()
            ->where('parent_id', $template->id)
            ->where('status', TemplateStatus::Draft)
            ->first();

        // One open draft per published version, or two people editing the same
        // template would silently create diverging v2s.
        if ($existingDraft !== null) {
            throw ValidationException::withMessages([
                'status' => "A draft of version {$template->version} already exists (template #{$existingDraft->id}). Edit or discard it first.",
            ]);
        }

        return DB::transaction(function () use ($template): ActivityTemplate {
            $draft = $this->copy($template, [
                'name' => $template->name,
                'version' => $this->nextVersionFor($template),
                'parent_id' => $template->id,
                'is_default' => false,
            ]);

            return $draft->load('items');
        });
    }

    /**
     * Copies a template into a fresh draft under a new name.
     *
     * Cloning starts a new version line at v1 — unlike versioning, which
     * continues an existing one.
     */
    public function clone(ActivityTemplate $template, string $name): ActivityTemplate
    {
        return DB::transaction(function () use ($template, $name): ActivityTemplate {
            $template->loadMissing('items');

            $clone = $this->copy($template, [
                'name' => $name,
                'version' => 1,
                'parent_id' => $template->id,
                'is_default' => false,
            ]);

            return $clone->load('items');
        });
    }

    /**
     * Archives a template instead of deleting it.
     *
     * A published version may already be in use, and a seeded default would
     * simply come back at the next provisioning run.
     */
    public function archive(ActivityTemplate $template): ActivityTemplate
    {
        $template->update([
            'status' => TemplateStatus::Archived,
            'is_active' => false,
        ]);

        return $template->fresh(['items', 'projectType']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function copy(ActivityTemplate $source, array $overrides): ActivityTemplate
    {
        $copy = ActivityTemplate::query()->create(array_merge([
            'project_type_id' => $source->project_type_id,
            'description' => $source->description,
            'is_sequential' => $source->is_sequential,
            'is_active' => true,
            'status' => TemplateStatus::Draft,
            'published_at' => null,
        ], $overrides));

        foreach ($source->items as $item) {
            $copy->items()->create([
                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'due_offset_days' => $item->due_offset_days,
                'config' => $item->config,
                'blocks_progression' => $item->blocks_progression,
                'sort_order' => $item->sort_order,
            ]);
        }

        return $copy;
    }

    /** Highest version in this template's line, plus one. */
    private function nextVersionFor(ActivityTemplate $template): int
    {
        $rootId = $template->rootId();

        $highest = ActivityTemplate::query()
            ->where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->max('version');

        return (int) max((int) $highest, (int) $template->version) + 1;
    }

    /**
     * Replaces a template's items wholesale.
     *
     * The editor submits the full ordered list; a partial merge would strand
     * rows the coordinator removed. `sort_order` is reassigned from array
     * position so the stored order always matches what was submitted.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(ActivityTemplate $template, array $items): void
    {
        $template->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $type = $item['type'] instanceof ActivityItemType
                ? $item['type']
                : ActivityItemType::from((string) ($item['type'] ?? ActivityItemType::Task->value));

            $template->items()->create([
                'type' => $type,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'due_offset_days' => $item['due_offset_days'] ?? null,
                'config' => $this->cleanConfig($type, $item['config'] ?? []),
                'blocks_progression' => $item['blocks_progression'] ?? $type->blocksByDefault(),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Keeps only the keys the type recognises, so a typo cannot be stored and
     * silently ignored.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function cleanConfig(ActivityItemType $type, array $config): ?array
    {
        $clean = array_intersect_key($config, array_flip($type->configKeys()));

        return $clean === [] ? null : $clean;
    }

    /**
     * @throws ValidationException
     */
    private function assertEditable(ActivityTemplate $template): void
    {
        if ($template->isEditable()) {
            return;
        }

        $message = $template->status === TemplateStatus::Published
            ? 'Published templates are immutable. Create a new version to make changes.'
            : 'Archived templates cannot be edited.';

        throw ValidationException::withMessages(['status' => $message]);
    }

    /**
     * Checks a template makes sense before it becomes usable.
     *
     * @throws ValidationException
     */
    private function assertCoherent(ActivityTemplate $template): void
    {
        $errors = [];
        $items = $template->items;

        if ($items->isEmpty()) {
            $errors['items'] = 'A template needs at least one item before it can be published.';
        }

        if ($items->count() > self::MAX_ITEMS) {
            $errors['items'] = 'A template cannot hold more than '.self::MAX_ITEMS.' items.';
        }

        foreach ($items as $item) {
            $missing = array_diff(
                array_keys(array_filter(
                    $item->type->configRules(),
                    fn (array $rules): bool => in_array('required', $rules, true),
                )),
                array_keys($item->config ?? []),
            );

            if ($missing !== []) {
                $errors['items'] = "\"{$item->title}\" is a {$item->type->label()} and is missing: "
                    .implode(', ', $missing).'.';
                break;
            }
        }

        if ($template->is_sequential) {
            $errors = array_merge($errors, $this->sequentialErrors($template));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Extra rules that only apply once a template enforces ordering.
     *
     * @return array<string, string>
     */
    private function sequentialErrors(ActivityTemplate $template): array
    {
        $errors = [];
        $items = $template->items->sortBy('sort_order')->values();

        // Ordering is meaningless if two items claim the same position.
        $orders = $items->pluck('sort_order');
        if ($orders->unique()->count() !== $orders->count()) {
            $errors['is_sequential'] = 'A sequential template cannot have two items in the same position.';

            return $errors;
        }

        // Offsets must not run backwards, or "next" and "later" disagree.
        $previousEnd = null;
        foreach ($items as $item) {
            $start = $item->due_offset_days;

            if ($start === null) {
                $errors['is_sequential'] = "\"{$item->title}\" has no due offset, which a sequential template needs to order by.";
                break;
            }

            if ($previousEnd !== null && $start < $previousEnd) {
                $errors['is_sequential'] = "\"{$item->title}\" is due on day {$start}, before the item preceding it finishes on day {$previousEnd}.";
                break;
            }

            $previousEnd = $item->endOffsetDays();
        }

        // A gate that blocks nothing is a no-op; flag the trailing case.
        $last = $items->last();
        if ($last !== null && $last->blocks_progression) {
            $errors['items'] = "\"{$last->title}\" blocks progression but is the last item, so it blocks nothing.";
        }

        return $errors;
    }
}
