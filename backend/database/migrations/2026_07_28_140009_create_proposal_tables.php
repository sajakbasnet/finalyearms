<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->text('background')->nullable();
            $table->text('problem_statement')->nullable();
            $table->text('objectives')->nullable();
            $table->text('scope')->nullable();
            $table->text('methodology')->nullable();
            $table->text('literature_review')->nullable();
            $table->text('timeline')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->text('technologies')->nullable();
            $table->text('references')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'version_number']);
        });

        Schema::create('proposal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->text('comment');
            $table->string('action')->nullable();
            $table->timestamps();
        });

        Schema::create('proposal_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reply');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_replies');
        Schema::dropIfExists('proposal_comments');
        Schema::dropIfExists('proposal_versions');
    }
};
