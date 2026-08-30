<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;

/**
 * Flags proposals that look like earlier ones.
 *
 * A **warning**, never a block. Overlap with previous work is often legitimate
 * — a continuation, a replication, a shared problem domain — and refusing it
 * would turn the check into something to route around rather than read.
 *
 * Deliberately no external dependency and no database-specific full-text
 * search: this has to behave identically on SQLite in tests and Postgres in
 * production. Titles are compared with similar_text(), abstracts by how much of
 * their vocabulary they share, which is cheap and good enough to prompt a human
 * to look.
 */
final class ProposalDuplicateChecker
{
    /** Words too common to say anything about similarity. */
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has',
        'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the', 'this', 'to', 'was',
        'were', 'will', 'with', 'using', 'based', 'system', 'project', 'study',
    ];

    /**
     * Earlier proposals resembling this title and abstract.
     *
     * @return list<array{project_id: int, title: string, session: string|null, title_similarity: float, abstract_similarity: float, reason: string}>
     */
    public function check(string $title, ?string $abstract = null, ?int $excludeProjectId = null): array
    {
        $titleThreshold = (float) config('projects.duplicates.title_threshold');
        $abstractThreshold = (float) config('projects.duplicates.abstract_threshold');
        $limit = (int) config('projects.duplicates.max_matches');

        $candidates = Project::query()
            // latestProposal uses latestOfMany(), which joins a subquery — naming
            // columns on it makes project_id ambiguous.
            ->with(['academicSession:id,name', 'latestProposal'])
            ->when($excludeProjectId !== null, fn ($query) => $query->whereKeyNot($excludeProjectId))
            ->whereNotNull('title')
            ->get();

        $matches = [];

        foreach ($candidates as $candidate) {
            $titleScore = $this->similarity($title, (string) $candidate->title);
            $abstractScore = $abstract === null
                ? 0.0
                : $this->vocabularyOverlap($abstract, (string) $candidate->latestProposal?->abstract);

            $byTitle = $titleScore >= $titleThreshold;
            $byAbstract = $abstractScore >= $abstractThreshold;

            if (! $byTitle && ! $byAbstract) {
                continue;
            }

            $matches[] = [
                'project_id' => (int) $candidate->id,
                'title' => (string) $candidate->title,
                'session' => $candidate->academicSession?->name,
                'title_similarity' => round($titleScore, 2),
                'abstract_similarity' => round($abstractScore, 2),
                'reason' => match (true) {
                    $byTitle && $byAbstract => 'The title and abstract both closely match this project.',
                    $byTitle => 'The title closely matches this project.',
                    default => 'The abstract covers much the same ground as this project.',
                },
            ];
        }

        // Strongest first, so the most likely duplicate is the one read.
        usort(
            $matches,
            fn (array $a, array $b): int => ($b['title_similarity'] + $b['abstract_similarity'])
                <=> ($a['title_similarity'] + $a['abstract_similarity']),
        );

        return array_slice($matches, 0, $limit);
    }

    /** Case- and punctuation-insensitive similarity, 0.0 to 1.0. */
    private function similarity(string $left, string $right): float
    {
        $left = $this->normalise($left);
        $right = $this->normalise($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);

        return $percent / 100;
    }

    /**
     * Share of meaningful vocabulary two passages have in common.
     *
     * Jaccard over word sets: order-insensitive, so a reworded abstract with
     * the same content still scores highly, which is the case worth flagging.
     */
    private function vocabularyOverlap(string $left, ?string $right): float
    {
        if ($right === null || trim($right) === '') {
            return 0.0;
        }

        $a = $this->significantWords($left);
        $b = $this->significantWords($right);

        if ($a === [] || $b === []) {
            return 0.0;
        }

        $shared = count(array_intersect($a, $b));
        $total = count(array_unique(array_merge($a, $b)));

        return $total === 0 ? 0.0 : $shared / $total;
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) > 2 && ! in_array($word, self::STOP_WORDS, true),
        )));
    }

    private function normalise(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(
            preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? '',
        )) ?? '');
    }
}
