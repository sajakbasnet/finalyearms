<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project asking a supervisor to take it on.
 *
 * Its own table rather than a status on `supervisor_assignments`: that table is
 * unique per (student, session), so a declined request would occupy the slot
 * permanently and block asking anyone else. Here a project can be declined,
 * approach someone else, and keep the trail of both.
 *
 * Requests are per **project**, not per student — a team asks once, as a team.
 * Accepting writes the per-student assignment rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            // The student who sent it — the team lead, or the student on an
            // individual project.
            $table->foreignId('requested_by')->nullable()->constrained('students')->nullOnDelete();

            $table->string('status', 20)->default('pending')->index();
            // Why this supervisor: the case the team makes for itself.
            $table->text('rationale');
            // Why not, when declined. Shown to the team so they can act on it.
            $table->string('response_note', 500)->nullable();

            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_requests');
    }
};
