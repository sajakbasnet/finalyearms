<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points students at a managed batch.
 *
 * Nullable and additive: the free-text `batch` column still holds the truth at
 * this point. It is backfilled by the next migration and dropped by the one
 * after, so each step is reversible on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('academic_session_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
