<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key dates within an academic session — proposal deadline, defence week,
 * results published, and so on.
 *
 * Activity templates anchor on day-offsets from a project start so they stay
 * reusable across sessions. These rows are the other half of that: the fixed
 * institutional dates a session actually runs to, which offsets cannot express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_session_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->date('date');
            // Deadlines are enforceable; other entries are informational.
            $table->boolean('is_deadline')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'label']);
            $table->index(['academic_session_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_session_dates');
    }
};
