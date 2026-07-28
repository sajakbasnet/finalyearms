<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_assignments');
    }
};
