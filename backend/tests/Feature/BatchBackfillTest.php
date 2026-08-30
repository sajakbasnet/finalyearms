<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The batch backfill migration.
 *
 * `migrate:fresh` runs it against an empty students table, so nothing else
 * exercises it — yet it is the migration that decides whether an existing
 * deployment keeps its cohort data.
 *
 * These tests load the real migration file and invoke its `up()`, so it is the
 * shipped code under test rather than a restatement of it. The dropped
 * free-text column is restored first, recreating the schema the migration
 * actually runs against.
 */
final class BatchBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_08_30_033712_backfill_student_batches.php',
        );
    }

    /**
     * Puts the database back into its pre-migration shape: the free-text column
     * present and populated, no batches, no batch_id.
     */
    private function seedLegacyStudents(string $csLabel = '2022 Intake', ?string $seLabel = '2022 Intake'): void
    {
        $this->seed();

        Schema::table('students', function ($table): void {
            $table->string('batch')->nullable();
        });

        DB::table('students')->update(['batch_id' => null]);
        DB::table('batches')->delete();

        $cs = DB::table('departments')->where('code', 'CS')->value('id');
        $se = DB::table('departments')->where('code', 'SE')->value('id');
        $ids = DB::table('students')->orderBy('id')->pluck('id')->all();

        DB::table('students')->where('id', $ids[0])
            ->update(['department_id' => $cs, 'batch' => $csLabel]);
        DB::table('students')->where('id', $ids[1])
            ->update(['department_id' => $se, 'batch' => $seLabel]);
    }

    public function test_it_creates_one_batch_per_department_and_label(): void
    {
        $this->seedLegacyStudents();

        $this->migration()->up();

        // The same label in two departments is two distinct cohorts.
        $this->assertSame(2, DB::table('batches')->count());
        $this->assertSame(2, DB::table('batches')->where('name', '2022 Intake')->count());
    }

    public function test_every_student_points_at_a_batch_in_their_own_department(): void
    {
        $this->seedLegacyStudents();

        $this->migration()->up();

        $mismatched = DB::table('students')
            ->join('batches', 'batches.id', '=', 'students.batch_id')
            ->whereColumn('batches.department_id', '!=', 'students.department_id')
            ->count();

        $this->assertSame(0, $mismatched);
        $this->assertSame(0, DB::table('students')->whereNull('batch_id')->count());
    }

    public function test_it_parses_the_intake_year_out_of_the_label(): void
    {
        $this->seedLegacyStudents('Batch 2021', '2079 Intake');

        $this->migration()->up();

        $this->assertSame(1, DB::table('batches')->where('intake_year', 2021)->count());
        $this->assertSame(1, DB::table('batches')->where('intake_year', 2079)->count());
    }

    /**
     * A label with no year in it must still produce a usable batch rather than
     * a zero year or a failed migration.
     */
    public function test_a_label_without_a_year_falls_back_to_the_session_start(): void
    {
        $this->seedLegacyStudents('Autumn cohort', 'Spring cohort');

        $this->migration()->up();

        $this->assertSame(2, DB::table('batches')->count());
        $this->assertSame(0, DB::table('batches')->where('intake_year', 0)->count());
        // The seeded session starts in 2026.
        $this->assertSame(2, DB::table('batches')->where('intake_year', 2026)->count());
        $this->assertSame(0, DB::table('students')->whereNull('batch_id')->count());
    }

    public function test_students_without_a_label_are_left_alone(): void
    {
        $this->seedLegacyStudents();
        DB::table('students')->update(['batch' => null]);

        $this->migration()->up();

        $this->assertSame(0, DB::table('batches')->count());
        $this->assertSame(0, DB::table('students')->whereNotNull('batch_id')->count());
    }

    public function test_an_empty_label_is_treated_as_no_label(): void
    {
        $this->seedLegacyStudents();
        DB::table('students')->update(['batch' => '']);

        $this->migration()->up();

        $this->assertSame(0, DB::table('batches')->count());
    }

    public function test_rolling_back_unlinks_students_and_removes_batches(): void
    {
        $this->seedLegacyStudents();
        $migration = $this->migration();

        $migration->up();
        $this->assertGreaterThan(0, DB::table('batches')->count());

        $migration->down();

        $this->assertSame(0, DB::table('batches')->count());
        $this->assertSame(0, DB::table('students')->whereNotNull('batch_id')->count());
    }
}
