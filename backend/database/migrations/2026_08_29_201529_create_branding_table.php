<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant branding.
 *
 * Deliberately a single-row settings table rather than a keyed store: the
 * columns are a fixed, known set and this database already belongs to exactly
 * one institution, so there is nothing to key by.
 *
 * Colours are stored as hex strings. Only these four are tenant-supplied — the
 * rest of the palette is derived from them in the frontend, and the status
 * colours (danger/success) are never themed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding', function (Blueprint $table): void {
            $table->id();

            $table->string('institution_name')->default('FYP Portal');
            $table->string('short_name')->nullable();
            $table->string('tagline')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            $table->string('color_primary', 7)->nullable();
            $table->string('color_accent', 7)->nullable();
            $table->string('color_ink', 7)->nullable();
            $table->string('color_paper', 7)->nullable();

            $table->string('font_display')->nullable();
            $table->string('font_sans')->nullable();
            $table->string('font_stylesheet_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding');
    }
};
