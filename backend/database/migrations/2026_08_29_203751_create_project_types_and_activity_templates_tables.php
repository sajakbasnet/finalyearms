<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinator-owned templates.
 *
 * `project_types` classify what kind of work a project is (Research,
 * Development, Internship...). `activity_templates` are the reusable milestone
 * skeletons a coordinator applies to a project so every team starts from the
 * same timeline.
 *
 * Both are seeded with defaults at tenant provisioning and are editable
 * afterwards, so `is_default` marks the seeded rows: it lets the UI distinguish
 * "shipped with the platform" from "added by this institution", and lets
 * re-seeding be idempotent without clobbering local edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_employer')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('activity_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Offset from project start, so a template is reusable across
            // sessions with different calendars.
            $table->unsignedInteger('due_offset_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['activity_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_template_items');
        Schema::dropIfExists('activity_templates');
        Schema::dropIfExists('project_types');
    }
};
