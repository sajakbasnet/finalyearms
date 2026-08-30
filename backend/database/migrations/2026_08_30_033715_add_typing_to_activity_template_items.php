<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives template items a type and a place to keep type-specific settings.
 *
 * `config` is JSON rather than a spread of nullable columns: an approval gate's
 * approver role means nothing to a recurring log, and institutions can extend a
 * type without a migration. App\Enums\ActivityItemType::configRules() validates
 * it per type and rejects unknown keys, so the looseness stops at the boundary.
 *
 * Existing rows become plain tasks, which is what they already were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_template_items', function (Blueprint $table): void {
            $table->string('type', 40)->default('task')->after('activity_template_id')->index();
            $table->json('config')->nullable()->after('due_offset_days');
            // In a sequential template, later items cannot start until this one
            // completes. Approval gates default to true; see the enum.
            $table->boolean('blocks_progression')->default(false)->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('activity_template_items', function (Blueprint $table): void {
            $table->dropColumn(['type', 'config', 'blocks_progression']);
        });
    }
};
