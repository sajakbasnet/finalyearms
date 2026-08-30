<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioning, publishing and lineage for activity templates.
 *
 * Published versions are immutable. Editing one forks a new draft at the next
 * version number rather than mutating in place, so a template cannot silently
 * rewrite the plan of a project that has already adopted it.
 *
 * `parent_id` records where a version or clone came from, giving each template
 * a traceable ancestry without a separate lineage table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_templates', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('name');
            $table->string('status', 20)->default('draft')->after('version')->index();
            $table->timestamp('published_at')->nullable()->after('status');

            $table->foreignId('parent_id')->nullable()->after('project_type_id')
                ->constrained('activity_templates')->nullOnDelete();

            // When true, items must be completed in sort_order.
            $table->boolean('is_sequential')->default(false)->after('is_default');

            $table->index(['status', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_templates', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['version', 'status', 'published_at', 'parent_id', 'is_sequential']);
        });
    }
};
