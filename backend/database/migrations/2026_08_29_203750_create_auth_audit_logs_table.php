<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentication event trail.
 *
 * Separate from `activity_logs`, which records domain changes by an
 * authenticated user. This table records the authentication boundary itself —
 * including failures, where there is no user to attribute the row to.
 *
 * `email` is stored alongside `user_id` on purpose: a failed login may name an
 * account that does not exist, and that is exactly the case worth auditing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_audit_logs', function (Blueprint $table): void {
            $table->id();

            // Null for failed logins against unknown accounts, and retained
            // (nullOnDelete) so deleting a user does not erase their trail.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event')->index();
            $table->string('email')->nullable()->index();
            $table->boolean('succeeded')->default(true);
            $table->string('reason')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_audit_logs');
    }
};
