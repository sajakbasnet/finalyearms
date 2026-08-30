<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to join a team.
 *
 * A student joins by accepting rather than being added, so membership is always
 * something they agreed to. Declined and cancelled rows are kept so a lead can
 * see who was asked and what came back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            // The invitee.
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // The lead who sent it. Retained if their account is removed.
            $table->foreignId('invited_by')->nullable()->constrained('students')->nullOnDelete();

            $table->string('status', 20)->default('pending')->index();
            $table->string('message', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // One live invitation per student per team. Re-inviting after a
            // decline updates the existing row rather than stacking duplicates.
            $table->unique(['student_group_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
