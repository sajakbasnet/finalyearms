<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('final_submissions')) {
            Schema::create('final_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('thesis_path')->nullable();
                $table->string('presentation_path')->nullable();
                $table->string('poster_path')->nullable();
                $table->string('source_code_path')->nullable();
                $table->string('documentation_path')->nullable();
                $table->string('demo_video_path')->nullable();
                $table->string('github_repository')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('final_submissions');
    }
};
