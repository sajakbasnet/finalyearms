<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('supervisor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->boolean('is_individual')->default(true);
            $table->timestamps();
        });

        Schema::create('student_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->timestamps();

            $table->unique(['student_group_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
    }
};
