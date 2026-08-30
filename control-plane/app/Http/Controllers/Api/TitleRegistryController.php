<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTitle;
use App\Models\Tenant;
use App\Services\TitleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The cross-institution project title registry.
 *
 * `check` is public: a student anywhere should be able to find out whether
 * their topic has been done, and requiring credentials for that would defeat
 * the point. Only approved titles are ever in here, and only the title,
 * institution and year are returned — never abstracts, authors or contact
 * details.
 *
 * `store` is authenticated with a per-tenant registry token, so a tenant can
 * append its own approved titles and nothing else.
 */
final class TitleRegistryController extends Controller
{
    public function __construct(
        private readonly TitleRegistry $registry,
    ) {}

    /** Public. Rate-limited, since it is the only unauthenticated write-free surface. */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:255'],
            'threshold' => ['nullable', 'numeric', 'min:0.3', 'max:1'],
        ]);

        $matches = $this->registry->search(
            $validated['title'],
            (float) ($validated['threshold'] ?? 0.75),
        );

        return response()->json([
            'data' => [
                'query' => $validated['title'],
                'exists' => collect($matches)->contains('is_exact', true),
                'match_count' => count($matches),
                'matches' => $matches,
            ],
            // Said plainly, because the check is advisory: overlap with earlier
            // work is often legitimate.
            'note' => $matches === []
                ? 'No approved project with a similar title was found.'
                : 'Similar approved projects exist. Overlap may still be fine — read them before deciding.',
        ]);
    }

    /** Records an approved title. Requires a tenant registry token. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:4', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:5000'],
            'academic_session' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'approved_at' => ['nullable', 'date'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('registry_tenant');

        $record = $this->registry->record($tenant, $validated);

        return response()->json([
            'message' => 'Title recorded in the registry.',
            'data' => [
                'id' => $record->id,
                'title' => $record->title,
                'normalised_title' => $record->normalised_title,
                'year' => $record->year,
            ],
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    /** How much is in the registry — useful for a status page, discloses nothing. */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'titles' => ProjectTitle::query()->count(),
                'institutions' => ProjectTitle::query()->distinct('tenant_id')->count('tenant_id'),
            ],
        ]);
    }
}
