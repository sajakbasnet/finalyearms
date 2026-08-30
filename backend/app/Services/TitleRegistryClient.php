<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to the control plane's cross-institution title registry.
 *
 * The registry answers "has this topic been done at another institution?",
 * which no single tenant database can know. Titles are contributed **only once
 * a proposal is approved** — a draft or a rejected proposal is not a claim on a
 * topic.
 *
 * Every call is best-effort. The registry being slow or down must never stop a
 * student saving a proposal or a supervisor approving one, so failures are
 * reported and swallowed rather than thrown. A tenant with no registry
 * configured simply behaves as if it found nothing.
 */
final class TitleRegistryClient
{
    public function isConfigured(): bool
    {
        return filled(config('projects.registry.url'));
    }

    /**
     * Approved titles elsewhere that resemble this one.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $title): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('projects.registry.timeout'))
                ->acceptJson()
                ->get($this->url('/api/titles/check'), ['title' => $title]);

            if (! $response->successful()) {
                return [];
            }

            /** @var list<array<string, mixed>> $matches */
            $matches = $response->json('data.matches', []);

            return $matches;
        } catch (Throwable $e) {
            // Advisory information: a student must still be able to work.
            report($e);

            return [];
        }
    }

    /**
     * Contributes an approved title.
     *
     * Returns whether it was recorded, so a caller can log the outcome without
     * having to care about the transport.
     */
    public function record(Project $project): bool
    {
        if (! $this->isConfigured() || blank(config('projects.registry.token'))) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('projects.registry.timeout'))
                ->withToken((string) config('projects.registry.token'))
                ->acceptJson()
                ->post($this->url('/api/titles'), [
                    'project_id' => $project->id,
                    'title' => $project->title,
                    'abstract' => $project->latestProposal?->abstract,
                    'academic_session' => $project->academicSession?->name,
                    'year' => $project->academicSession?->start_date?->year,
                    'approved_at' => now()->toIso8601String(),
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            // An approval must not fail because the registry was unreachable.
            // The title can be backfilled later.
            report($e);

            return false;
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) config('projects.registry.url'), '/').$path;
    }
}
