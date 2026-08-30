<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coordinator;

use App\Enums\ActivityItemType;
use App\Enums\RecurringCadence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\CloneActivityTemplateRequest;
use App\Http\Requests\Coordinator\StoreActivityTemplateRequest;
use App\Http\Requests\Coordinator\UpdateActivityTemplateRequest;
use App\Models\ActivityTemplate;
use App\Models\ActivityTemplateItem;
use App\Services\ActivityTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityTemplateController extends Controller
{
    public function __construct(
        private readonly ActivityTemplateService $templates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = ActivityTemplate::query()
            ->with(['items', 'projectType:id,name,slug'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
            )
            ->when(
                $request->filled('project_type_id'),
                fn ($query) => $query->where('project_type_id', $request->integer('project_type_id')),
            )
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => $templates->map(fn (ActivityTemplate $t) => $this->map($t)),
        ]);
    }

    /** The item types and cadences a builder UI can offer. */
    public function itemTypes(): JsonResponse
    {
        return response()->json([
            'data' => [
                'item_types' => array_map(fn (ActivityItemType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'repeats' => $type->repeats(),
                    'blocks_by_default' => $type->blocksByDefault(),
                    'config_keys' => $type->configKeys(),
                ], ActivityItemType::cases()),
                'cadences' => array_map(fn (RecurringCadence $cadence) => [
                    'value' => $cadence->value,
                    'label' => $cadence->label(),
                    'interval_days' => $cadence->intervalDays(),
                ], RecurringCadence::cases()),
            ],
        ]);
    }

    public function show(ActivityTemplate $activityTemplate): JsonResponse
    {
        return response()->json([
            'data' => $this->map($activityTemplate->load(['items', 'projectType:id,name,slug'])),
        ]);
    }

    public function store(StoreActivityTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->validated());

        return response()->json([
            'message' => 'Activity template created as a draft.',
            'data' => $this->map($template->load('projectType:id,name,slug')),
        ], 201);
    }

    public function update(
        UpdateActivityTemplateRequest $request,
        ActivityTemplate $activityTemplate,
    ): JsonResponse {
        $template = $this->templates->update($activityTemplate, $request->validated());

        return response()->json([
            'message' => 'Activity template updated successfully.',
            'data' => $this->map($template),
        ]);
    }

    /** Makes a draft usable, and immutable. */
    public function publish(ActivityTemplate $activityTemplate): JsonResponse
    {
        $template = $this->templates->publish($activityTemplate);

        return response()->json([
            'message' => "Version {$template->version} published and ready to use.",
            'data' => $this->map($template),
        ]);
    }

    /** Opens the next draft version of a published template. */
    public function newVersion(ActivityTemplate $activityTemplate): JsonResponse
    {
        $draft = $this->templates->newVersion($activityTemplate);

        return response()->json([
            'message' => "Draft version {$draft->version} created. The published version is unchanged.",
            'data' => $this->map($draft->load('projectType:id,name,slug')),
        ], 201);
    }

    /** Copies a template into a new draft under a new name. */
    public function clone(
        CloneActivityTemplateRequest $request,
        ActivityTemplate $activityTemplate,
    ): JsonResponse {
        $clone = $this->templates->clone(
            $activityTemplate,
            $request->string('name')->toString(),
        );

        return response()->json([
            'message' => 'Template cloned as a new draft.',
            'data' => $this->map($clone->load('projectType:id,name,slug')),
        ], 201);
    }

    /**
     * Archives rather than deletes.
     *
     * A published version may already be in use, and a seeded default would
     * return at the next provisioning run, so archiving is the only removal
     * that actually sticks.
     */
    public function destroy(ActivityTemplate $activityTemplate): JsonResponse
    {
        $template = $this->templates->archive($activityTemplate);

        return response()->json([
            'message' => 'Activity template archived.',
            'data' => $this->map($template),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(ActivityTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'version' => $template->version,
            'status' => $template->status->value,
            'status_label' => $template->status->label(),
            'published_at' => $template->published_at?->toIso8601String(),
            'is_default' => $template->is_default,
            'is_active' => $template->is_active,
            'is_sequential' => $template->is_sequential,
            'is_editable' => $template->isEditable(),
            'is_usable' => $template->isUsable(),
            'parent_id' => $template->parent_id,
            'span_days' => $template->relationLoaded('items') ? $template->spanDays() : null,
            'project_type' => $template->projectType === null ? null : [
                'id' => $template->projectType->id,
                'name' => $template->projectType->name,
                'slug' => $template->projectType->slug,
            ],
            'items' => $template->relationLoaded('items')
                ? $template->items->map(fn (ActivityTemplateItem $item) => [
                    'id' => $item->id,
                    'type' => $item->type->value,
                    'type_label' => $item->type->label(),
                    'title' => $item->title,
                    'description' => $item->description,
                    'due_offset_days' => $item->due_offset_days,
                    'end_offset_days' => $item->endOffsetDays(),
                    'config' => $item->config,
                    'blocks_progression' => $item->blocks_progression,
                    'sort_order' => $item->sort_order,
                ])->all()
                : [],
        ];
    }
}
