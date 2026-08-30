<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectTitle;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Lookup and contribution for the cross-institution title registry.
 *
 * Matching is deliberately plain PHP string work rather than database
 * full-text search, so it behaves identically on SQLite in tests and Postgres
 * in production, and so the same algorithm can be described to a student.
 */
final class TitleRegistry
{
    /** Words too common to say anything about similarity. */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has',
        'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'this', 'to', 'was',
        'were', 'will', 'with', 'using', 'based', 'system', 'project', 'study',
    ];

    /**
     * Registered titles resembling the one given.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $title, float $threshold = 0.75, int $limit = 10): array
    {
        $normalised = ProjectTitle::normalise($title);

        if ($normalised === '') {
            return [];
        }

        $matches = $this->candidates($normalised)
            ->map(function (ProjectTitle $row) use ($normalised): array {
                similar_text($normalised, $row->normalised_title, $percent);

                return [
                    'title' => $row->title,
                    'institution' => $row->tenant?->name,
                    'academic_session' => $row->academic_session,
                    'year' => $row->year,
                    'similarity' => round($percent / 100, 2),
                    'is_exact' => $row->normalised_title === $normalised,
                ];
            })
            ->filter(fn (array $match): bool => $match['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();

        return $matches->all();
    }

    /**
     * Records an approved title, or updates the one already held for that
     * project.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(Tenant $tenant, array $payload): ProjectTitle
    {
        return ProjectTitle::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'external_project_id' => (int) $payload['project_id'],
            ],
            [
                'title' => $payload['title'],
                'normalised_title' => ProjectTitle::normalise((string) $payload['title']),
                'abstract' => $payload['abstract'] ?? null,
                'academic_session' => $payload['academic_session'] ?? null,
                'year' => $payload['year'] ?? null,
                'approved_at' => $payload['approved_at'] ?? now(),
            ],
        );
    }

    /**
     * Narrows the table before scoring.
     *
     * An exact normalised match is the common case and is an index hit. Beyond
     * that, only rows sharing a significant word can plausibly score highly, so
     * everything else is excluded before any string comparison runs.
     */
    private function candidates(string $normalised): Collection
    {
        $words = $this->significantWords($normalised);

        return ProjectTitle::query()
            ->with('tenant:id,name')
            ->where(function ($query) use ($normalised, $words): void {
                $query->where('normalised_title', $normalised);

                foreach ($words as $word) {
                    $query->orWhere('normalised_title', 'like', '%'.$word.'%');
                }
            })
            // A hard ceiling: a very generic word must not pull the whole table
            // into memory.
            ->limit(500)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) > 3 && ! in_array($word, self::STOP_WORDS, true),
        )));
    }
}
