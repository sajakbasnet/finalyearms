<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the free-text batch column now that `batch_id` carries it.
 *
 * Rolling back restores the column but not its values — the preceding backfill
 * migration reverses the data, and these two must be rolled back together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('batch');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('batch')->nullable()->after('roll_number');
        });
    }
};
