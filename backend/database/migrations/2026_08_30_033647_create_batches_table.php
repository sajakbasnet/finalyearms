<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student intake cohorts.
 *
 * Scoped to a department, because "2079 Intake" in Computer Science is a
 * different cohort from the same-named intake in Software Engineering. A batch
 * outlives any single academic session, so it is deliberately not tied to one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('intake_year');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // A department cannot have two cohorts of the same name.
            $table->unique(['department_id', 'name']);
            $table->index(['department_id', 'intake_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
