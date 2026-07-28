<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('percentage_completed')->default(0);
            $table->string('demo_link')->nullable();
            $table->string('git_repository')->nullable();
            $table->string('status')->default('submitted');
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('progress_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->string('action')->nullable();
            $table->timestamps();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('innovation')->nullable();
            $table->unsignedTinyInteger('implementation')->nullable();
            $table->unsignedTinyInteger('documentation')->nullable();
            $table->unsignedTinyInteger('presentation')->nullable();
            $table->unsignedTinyInteger('testing')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'teacher_id']);
        });

        Schema::create('meeting_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->dateTime('meeting_date');
            $table->text('agenda')->nullable();
            $table->text('discussion')->nullable();
            $table->text('assigned_tasks')->nullable();
            $table->dateTime('next_meeting')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_logs');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('progress_comments');
        Schema::dropIfExists('progress_reports');
    }
};
