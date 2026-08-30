<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A narrow write token per tenant, for the title registry only.
 *
 * This is deliberately **not** database access. The rule that a tenant
 * container never holds control-plane database credentials still holds — this
 * token lets a tenant append its own approved titles and nothing else. It
 * cannot read the registry beyond the public endpoint, cannot see other
 * tenants' rows, and cannot touch the tenant registry.
 *
 * Stored hashed: a leaked database dump should not yield working tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('registry_token_hash', 64)->nullable()->unique()->after('admin_name');
            $table->timestamp('registry_token_issued_at')->nullable()->after('registry_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['registry_token_hash', 'registry_token_issued_at']);
        });
    }
};
