<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs(User::query()->where('email', 'admin@fyp.local')->firstOrFail());
    }

    private function currentSession(): AcademicSession
    {
        return AcademicSession::query()->firstOrFail();
    }

    /** A session with nothing pinned to it, so it can be freely edited. */
    private function spareSession(string $name = '2027-2028'): AcademicSession
    {
        return AcademicSession::query()->create([
            'name' => $name,
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'is_active' => false,
        ]);
    }

    // ---------------------------------------------------------------
    // Session lifecycle
    // ---------------------------------------------------------------

    public function test_an_admin_can_update_a_session(): void
    {
        $session = $this->spareSession();

        $this->putJson("/api/admin/sessions/{$session->id}", ['name' => '2027-28 (revised)'])
            ->assertOk()
            ->assertJsonPath('data.name', '2027-28 (revised)');
    }

    public function test_the_end_date_must_follow_the_start_date(): void
    {
        $session = $this->spareSession();

        $this->putJson("/api/admin/sessions/{$session->id}", [
            'start_date' => '2028-01-01',
            'end_date' => '2027-01-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    /**
     * Exactly one session is active: students, groups and projects all pin to
     * one, so two would make "the current session" ambiguous.
     */
    public function test_activating_a_session_stands_the_others_down(): void
    {
        $original = $this->currentSession();
        $this->assertTrue($original->is_active);

        $spare = $this->spareSession();

        $this->postJson("/api/admin/sessions/{$spare->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertFalse($original->fresh()->is_active);
        $this->assertSame(1, AcademicSession::query()->where('is_active', true)->count());
    }

    public function test_creating_an_active_session_stands_the_others_down(): void
    {
        $original = $this->currentSession();

        $this->postJson('/api/admin/sessions', [
            'name' => '2028-2029',
            'start_date' => '2028-09-01',
            'end_date' => '2029-06-30',
            'is_active' => true,
        ])->assertCreated();

        $this->assertFalse($original->fresh()->is_active);
        $this->assertSame(1, AcademicSession::query()->where('is_active', true)->count());
    }

    public function test_a_session_in_use_cannot_be_deleted(): void
    {
        // The seeded session has students and a project pinned to it.
        $this->deleteJson("/api/admin/sessions/{$this->currentSession()->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        $this->assertDatabaseHas('academic_sessions', ['id' => $this->currentSession()->id]);
    }

    public function test_the_active_session_cannot_be_deleted(): void
    {
        $spare = $this->spareSession();
        $this->postJson("/api/admin/sessions/{$spare->id}/activate")->assertOk();

        $this->deleteJson("/api/admin/sessions/{$spare->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
    }

    public function test_an_unused_inactive_session_can_be_deleted(): void
    {
        $spare = $this->spareSession();

        $this->deleteJson("/api/admin/sessions/{$spare->id}")->assertOk();

        $this->assertDatabaseMissing('academic_sessions', ['id' => $spare->id]);
    }

    // ---------------------------------------------------------------
    // Key dates
    // ---------------------------------------------------------------

    public function test_an_admin_can_add_a_key_date(): void
    {
        $session = $this->currentSession();

        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Proposal deadline',
            'date' => '2026-10-15',
            'is_deadline' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Proposal deadline')
            ->assertJsonPath('data.is_deadline', true);
    }

    /**
     * A key date outside its own session is almost always a typo, and accepting
     * it silently produces a calendar nobody can trust.
     */
    public function test_a_date_outside_the_session_is_rejected(): void
    {
        $session = $this->currentSession();

        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Way off',
            'date' => '2030-01-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_key_dates_are_listed_in_date_order(): void
    {
        $session = $this->currentSession();

        foreach ([['Defence week', '2027-05-01'], ['Proposal deadline', '2026-10-15']] as [$label, $date]) {
            $this->postJson("/api/admin/sessions/{$session->id}/dates", compact('label', 'date'))
                ->assertCreated();
        }

        $this->getJson("/api/admin/sessions/{$session->id}/dates")
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Proposal deadline')
            ->assertJsonPath('data.1.label', 'Defence week');
    }

    public function test_a_duplicate_label_within_a_session_is_rejected(): void
    {
        $session = $this->currentSession();

        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Proposal deadline', 'date' => '2026-10-15',
        ])->assertCreated();

        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Proposal deadline', 'date' => '2026-11-15',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('label');
    }

    /**
     * Route model binding resolves both parameters independently, so without an
     * explicit check a date could be reached through the wrong session.
     */
    public function test_a_date_cannot_be_reached_through_another_session(): void
    {
        $session = $this->currentSession();
        $other = $this->spareSession();

        $dateId = $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Proposal deadline', 'date' => '2026-10-15',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/admin/sessions/{$other->id}/dates/{$dateId}", ['label' => 'Hijacked'])
            ->assertNotFound();

        $this->deleteJson("/api/admin/sessions/{$other->id}/dates/{$dateId}")
            ->assertNotFound();

        $this->assertDatabaseHas('academic_session_dates', ['id' => $dateId, 'label' => 'Proposal deadline']);
    }

    public function test_narrowing_a_session_past_its_key_dates_is_refused(): void
    {
        $session = $this->spareSession();

        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Defence week', 'date' => '2028-05-01',
        ])->assertCreated();

        // Pulling the end date back would strand the date already recorded.
        $this->putJson("/api/admin/sessions/{$session->id}", ['end_date' => '2027-12-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_a_key_date_can_be_removed(): void
    {
        $session = $this->currentSession();

        $dateId = $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Temporary', 'date' => '2026-12-01',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/admin/sessions/{$session->id}/dates/{$dateId}")->assertOk();

        $this->assertDatabaseMissing('academic_session_dates', ['id' => $dateId]);
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_a_coordinator_can_read_the_calendar_but_not_change_it(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'coordinator@fyp.local')->firstOrFail());
        $session = $this->currentSession();

        $this->getJson('/api/admin/sessions')->assertOk();
        $this->getJson("/api/admin/sessions/{$session->id}/dates")->assertOk();

        $this->putJson("/api/admin/sessions/{$session->id}", ['name' => 'Nope'])->assertForbidden();
        $this->postJson("/api/admin/sessions/{$session->id}/activate")->assertForbidden();
        $this->postJson("/api/admin/sessions/{$session->id}/dates", [
            'label' => 'Nope', 'date' => '2026-10-15',
        ])->assertForbidden();
    }
}
