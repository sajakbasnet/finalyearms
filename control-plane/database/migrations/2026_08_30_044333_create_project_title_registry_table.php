<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-institution registry of approved project titles.
 *
 * The one thing that genuinely has to be shared across tenants: a student at
 * one institution should be able to find out that their topic was already done
 * at another. Each tenant database is isolated, so the only place this can live
 * is the control plane.
 *
 * Titles arrive **only once a proposal is approved** — a draft or a rejected
 * proposal is not a claim on a topic.
 *
 * `normalised_title` is the lower-cased, punctuation-stripped form, stored so a
 * lookup is an index hit rather than a scan with per-row string mangling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_titles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('normalised_title')->index();
            $table->text('abstract')->nullable();

            // Kept so a match can say "2024, Tribhuvan University" rather than
            // just "this exists somewhere".
            $table->string('academic_session')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();

            // The tenant's own project id, so a tenant can reconcile its own
            // rows without the registry needing to understand them.
            $table->unsignedBigInteger('external_project_id');

            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // One row per project per tenant: re-posting an approval updates
            // rather than duplicating.
            $table->unique(['tenant_id', 'external_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_titles');
    }
};
