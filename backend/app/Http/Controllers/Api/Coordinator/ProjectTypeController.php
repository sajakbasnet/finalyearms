<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreProjectTypeRequest;
use App\Http\Requests\Coordinator\UpdateProjectTypeRequest;
use App\Models\ProjectType;
use Illuminate\Http\JsonResponse;

final class ProjectTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = ProjectType::query()
            ->withCount('activityTemplates')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $types->map(fn (ProjectType $type) => $this->map($type)),
        ]);
    }

    public function store(StoreProjectTypeRequest $request): JsonResponse
    {
        $type = ProjectType::query()->create($request->validated());

        return response()->json([
            'message' => 'Project type created successfully.',
            'data' => $this->map($type->loadCount('activityTemplates')),
        ], 201);
    }

    public function update(UpdateProjectTypeRequest $request, ProjectType $projectType): JsonResponse
    {
        $projectType->update($request->validated());

        return response()->json([
            'message' => 'Project type updated successfully.',
            'data' => $this->map($projectType->loadCount('activityTemplates')),
        ]);
    }

    public function destroy(ProjectType $projectType): JsonResponse
    {
        /*
         * Seeded defaults are deactivated rather than deleted. They may already
         * be referenced by templates, and re-provisioning would recreate them
         * anyway — leaving a coordinator unable to make the removal stick.
         */
        if ($projectType->is_default) {
            $projectType->update(['is_active' => false]);

            return response()->json([
                'message' => 'Default project type deactivated.',
                'data' => $this->map($projectType->loadCount('activityTemplates')),
            ]);
        }

        $projectType->delete();

        return response()->json([
            'message' => 'Project type deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function map(ProjectType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
            'description' => $type->description,
            'requires_employer' => $type->requires_employer,
            'is_active' => $type->is_active,
            'is_default' => $type->is_default,
            'sort_order' => $type->sort_order,
            'activity_templates_count' => $type->activity_templates_count ?? 0,
        ];
    }
}
