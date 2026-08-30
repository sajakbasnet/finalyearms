<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant registry.
 *
 * This database is the crown jewel: it is the only place that can reach every
 * institution. Tenant application containers never receive credentials for it —
 * they are given only their own database login, injected at provisioning time.
 *
 * Status and step values are backed by PHP enums rather than database enums so
 * the schema stays portable across Postgres (production) and SQLite (tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();

            // Subdomain label and the name component of the tenant's database
            // and container. Validated before it reaches any identifier.
            $table->string('slug', 40)->unique();
            $table->string('name');

            // Passed to the tenant container as TENANT_ADMIN_EMAIL so the
            // bootstrap seeder creates the institution's first Coordinator.
            $table->string('admin_email');
            $table->string('admin_name')->nullable();

            $table->string('status', 20)->default('provisioning')->index();
            $table->string('plan', 40)->nullable();
            $table->text('suspended_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });

        /*
         | Credentials. password and app_key are stored with the `encrypted`
         | cast, never in plaintext. A distinct APP_KEY per tenant is what stops
         | a signed or encrypted payload from one institution being valid at
         | another.
         */
        Schema::create('tenant_databases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('driver', 20)->default('pgsql');
            $table->string('host');
            $table->unsignedInteger('port')->default(5432);
            $table->string('database_name', 63)->unique();
            $table->string('username', 63)->unique();
            $table->text('password');
            $table->text('app_key');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });

        /*
         | schema_version is what makes migration fan-out survivable: after a
         | partial rollout it identifies exactly which tenants are behind.
         */
        Schema::create('tenant_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('service_name', 100);
            $table->string('image_tag', 100);
            $table->string('schema_version')->nullable()->index();
            $table->string('health', 20)->default('unknown');
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        /*
         | Provisioning state machine — one row per (tenant, step).
         |
         | Progress is recorded rather than held in memory because the
         | provisioner is expected to fail partway and be re-run; every step is
         | written to be idempotent.
         */
        Schema::create('provisioning_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->string('state', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'step']);
            $table->index(['tenant_id', 'state']);
        });

        // Answers "who provisioned, suspended, or deleted what, and when".
        Schema::create('control_plane_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()
                ->constrained('platform_admins')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100)->index();
            $table->json('detail')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_plane_audit_logs');
        Schema::dropIfExists('provisioning_jobs');
        Schema::dropIfExists('tenant_deployments');
        Schema::dropIfExists('tenant_databases');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
