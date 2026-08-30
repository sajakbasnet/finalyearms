<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectTitle;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cross-institution title registry.
 *
 * Public to read, token-guarded to write. The token is a narrow write
 * credential and grants nothing else — that boundary is what these tests are
 * mostly here to hold.
 */
final class TitleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'slug' => 'tu',
            'name' => 'Tribhuvan University',
            'admin_email' => 'coordinator@tu.edu.np',
        ]);

        $this->token = $this->tenant->issueRegistryToken();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(array $overrides = []): ProjectTitle
    {
        $title = $overrides['title'] ?? 'Smart Campus Attendance System';

        return ProjectTitle::query()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'title' => $title,
            'normalised_title' => ProjectTitle::normalise($title),
            'academic_session' => '2024-2025',
            'year' => 2024,
            'external_project_id' => random_int(1, 100000),
            'approved_at' => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Public lookup
    // ---------------------------------------------------------------

    public function test_the_check_endpoint_needs_no_credentials(): void
    {
        $this->record();

        $this->getJson('/api/titles/check?title=Smart Campus Attendance System')
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.matches.0.institution', 'Tribhuvan University')
            ->assertJsonPath('data.matches.0.year', 2024);
    }

    public function test_case_and_punctuation_do_not_hide_a_match(): void
    {
        $this->record();

        $this->getJson('/api/titles/check?title=smart campus, attendance system!')
            ->assertOk()
            ->assertJsonPath('data.exists', true);
    }

    public function test_an_unrelated_title_returns_nothing(): void
    {
        $this->record();

        $this->getJson('/api/titles/check?title=Groundwater Salinity Mapping in Coastal Wells')
            ->assertOk()
            ->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.match_count', 0);
    }

    /** Advisory, not a verdict — the wording has to say so. */
    public function test_the_response_frames_a_match_as_advisory(): void
    {
        $this->record();

        $body = $this->getJson('/api/titles/check?title=Smart Campus Attendance System')
            ->assertOk()->json();

        $this->assertStringContainsString('Overlap may still be fine', $body['note']);
    }

    /**
     * The registry answers "has this been done", not "who did it and how can I
     * contact them". Abstracts and any tenant internals stay out.
     */
    public function test_the_public_response_discloses_nothing_beyond_title_and_provenance(): void
    {
        $this->record(['abstract' => 'A face recognition system for laboratory attendance.']);

        $match = $this->getJson('/api/titles/check?title=Smart Campus Attendance System')
            ->assertOk()->json('data.matches.0');

        $this->assertSame(
            ['title', 'institution', 'academic_session', 'year', 'similarity', 'is_exact'],
            array_keys($match),
        );
        $this->assertArrayNotHasKey('abstract', $match);
        $this->assertArrayNotHasKey('tenant_id', $match);
    }

    public function test_a_title_is_required_and_must_be_substantial(): void
    {
        $this->getJson('/api/titles/check?title=ab')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->getJson('/api/titles/check')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_stats_expose_totals_only(): void
    {
        $this->record();

        $this->getJson('/api/titles/stats')
            ->assertOk()
            ->assertJsonPath('data.titles', 1)
            ->assertJsonPath('data.institutions', 1);
    }

    // ---------------------------------------------------------------
    // Contributing
    // ---------------------------------------------------------------

    public function test_a_tenant_can_record_an_approved_title(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/titles', [
                'project_id' => 42,
                'title' => 'Adaptive Irrigation Controller',
                'academic_session' => '2026-2027',
                'year' => 2026,
            ])
            ->assertCreated()
            ->assertJsonPath('data.normalised_title', 'adaptive irrigation controller');

        $this->assertDatabaseHas('project_titles', [
            'tenant_id' => $this->tenant->id,
            'external_project_id' => 42,
        ]);
    }

    /** Re-posting the same project updates rather than duplicating. */
    public function test_recording_the_same_project_twice_updates_it(): void
    {
        foreach (['First title', 'Revised title'] as $title) {
            $this->withToken($this->token)
                ->postJson('/api/titles', ['project_id' => 7, 'title' => $title])
                ->assertSuccessful();
        }

        $this->assertSame(1, ProjectTitle::query()->count());
        $this->assertSame('Revised title', ProjectTitle::query()->firstOrFail()->title);
    }

    public function test_contributing_without_a_token_is_refused(): void
    {
        $this->postJson('/api/titles', ['project_id' => 1, 'title' => 'Anything at all'])
            ->assertUnauthorized();

        $this->assertSame(0, ProjectTitle::query()->count());
    }

    public function test_a_wrong_token_is_refused(): void
    {
        $this->withToken('reg_'.str_repeat('0', 48))
            ->postJson('/api/titles', ['project_id' => 1, 'title' => 'Anything at all'])
            ->assertUnauthorized();
    }

    /** A leaked database dump must not yield working tokens. */
    public function test_only_a_hash_of_the_token_is_stored(): void
    {
        $stored = (string) $this->tenant->fresh()->registry_token_hash;

        $this->assertNotSame($this->token, $stored);
        $this->assertSame(hash('sha256', $this->token), $stored);
        $this->assertStringNotContainsString($this->token, $stored);
    }

    /** Reissuing revokes the previous token. */
    public function test_reissuing_invalidates_the_old_token(): void
    {
        $old = $this->token;
        $new = $this->tenant->issueRegistryToken();

        $this->withToken($old)
            ->postJson('/api/titles', ['project_id' => 1, 'title' => 'Should be refused'])
            ->assertUnauthorized();

        $this->withToken($new)
            ->postJson('/api/titles', ['project_id' => 1, 'title' => 'Should be accepted'])
            ->assertCreated();
    }

    /**
     * The token identifies a contributor and nothing more. It must not open a
     * door to the operator side of the control plane.
     */
    public function test_a_registry_token_grants_no_operator_access(): void
    {
        $this->withToken($this->token)->get('/tenants')->assertRedirect(route('login'));
        $this->withToken($this->token)->get('/')->assertRedirect(route('login'));
    }

    /** A tenant records under its own id, whatever it claims. */
    public function test_a_tenant_cannot_write_under_another_tenants_name(): void
    {
        $other = Tenant::query()->create([
            'slug' => 'ku',
            'name' => 'Kathmandu University',
            'admin_email' => 'c@ku.edu.np',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/titles', [
                'project_id' => 99,
                'title' => 'Attempted impersonation',
                'tenant_id' => $other->id,
            ])
            ->assertCreated();

        // Attribution comes from the token, not the payload.
        $this->assertDatabaseHas('project_titles', [
            'external_project_id' => 99,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_a_recorded_title_becomes_findable_publicly(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/titles', [
                'project_id' => 5,
                'title' => 'Adaptive Irrigation Controller',
                'year' => 2026,
            ])->assertCreated();

        $this->getJson('/api/titles/check?title=Adaptive Irrigation Controller')
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.matches.0.institution', 'Tribhuvan University');
    }
}
