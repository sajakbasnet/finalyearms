<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervisor capacity is counted in projects, not students.
 *
 * A supervisor takes several teams, and a team is 4-6 students — so a column
 * called `maximum_students` that actually limits teams would misread by a
 * factor of five. The rename keeps the meaning honest.
 *
 * Existing values carry over unchanged: a supervisor previously capped at 5
 * students is now capped at 5 projects. That is a deliberate widening, since
 * the old cap was never reachable under team formation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->renameColumn('maximum_students', 'max_projects');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->renameColumn('max_projects', 'maximum_students');
        });
    }
};
