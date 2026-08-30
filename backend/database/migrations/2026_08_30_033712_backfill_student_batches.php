<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turns the free-text `students.batch` values into real batch rows.
 *
 * Data only. Each distinct (department, batch label) becomes one batch, and the
 * students carrying that label are pointed at it.
 *
 * The intake year is parsed out of the label where it looks like a year
 * ("2079 Intake", "Batch 2022"); otherwise it falls back to the year the
 * student's academic session starts, which is the closest thing we know.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('students')
            ->whereNotNull('batch')
            ->where('batch', '!=', '')
            ->select('department_id', 'batch')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $intakeYear = $this->guessIntakeYear($row->batch, $row->department_id);

            $batchId = DB::table('batches')->insertGetId([
                'department_id' => $row->department_id,
                'name' => $row->batch,
                'intake_year' => $intakeYear,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('students')
                ->where('department_id', $row->department_id)
                ->where('batch', $row->batch)
                ->update(['batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        DB::table('students')->update(['batch_id' => null]);
        DB::table('batches')->delete();
    }

    private function guessIntakeYear(string $label, int $departmentId): int
    {
        if (preg_match('/(19|20|21)\d{2}/', $label, $m) === 1) {
            return (int) $m[0];
        }

        $sessionStart = DB::table('students')
            ->join('academic_sessions', 'academic_sessions.id', '=', 'students.academic_session_id')
            ->where('students.department_id', $departmentId)
            ->where('students.batch', $label)
            ->value('academic_sessions.start_date');

        return $sessionStart !== null
            ? (int) date('Y', strtotime((string) $sessionStart))
            : (int) date('Y');
    }
};
