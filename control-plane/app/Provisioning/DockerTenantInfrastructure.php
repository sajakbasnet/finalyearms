<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Real provisioning against Postgres and Docker Compose.
 *
 * Every method is idempotent — the pipeline may re-run any step after a
 * failure.
 *
 * NOTE: unverified end to end. Docker was unavailable in the environment this
 * was written in, so the pipeline's ordering and failure handling are covered
 * by tests against a fake, but these shell-outs are not.
 */
final class DockerTenantInfrastructure implements TenantInfrastructure
{
    public function createDatabase(Tenant $tenant): void
    {
        $name = $tenant->databaseIdentifier();

        $exists = $this->psql("SELECT 1 FROM pg_database WHERE datname = '{$name}'");

        if (trim($exists) === '') {
            // Identifier, not a bound parameter — CREATE DATABASE cannot take
            // one. Safe because databaseIdentifier() derives from a slug the
            // pipeline validated against ^[a-z][a-z0-9-]{1,38}[a-z0-9]$.
            $this->psql("CREATE DATABASE \"{$name}\"");
        }
    }

    public function createRole(Tenant $tenant): void
    {
        $database = $tenant->database;
        $user = $database->username;
        $password = str_replace("'", "''", (string) $database->password);
        $name = $tenant->databaseIdentifier();

        $exists = $this->psql("SELECT 1 FROM pg_roles WHERE rolname = '{$user}'");

        $this->psql(trim($exists) === ''
            ? "CREATE ROLE \"{$user}\" LOGIN PASSWORD '{$password}'"
            : "ALTER ROLE \"{$user}\" LOGIN PASSWORD '{$password}'");

        // Scoped to its own database only: this role must never be able to see
        // another tenant's data or the control plane.
        $this->psql("GRANT ALL PRIVILEGES ON DATABASE \"{$name}\" TO \"{$user}\"");
        $this->psql("ALTER DATABASE \"{$name}\" OWNER TO \"{$user}\"");
        $this->psql("REVOKE ALL ON DATABASE \"{$name}\" FROM PUBLIC");
    }

    public function runMigrations(Tenant $tenant): void
    {
        $this->artisan($tenant, ['migrate', '--force', '--isolated']);
    }

    public function seedBootstrap(Tenant $tenant): void
    {
        $this->artisan($tenant, [
            'db:seed',
            '--class=Database\\Seeders\\TenantBootstrapSeeder',
            '--force',
        ]);
    }

    public function startContainer(Tenant $tenant): void
    {
        $this->compose(['up', '-d', $tenant->serviceName()]);
    }

    public function isHealthy(Tenant $tenant): bool
    {
        $url = 'https://'.$tenant->primaryDomain?->domain.'/up';
        $attempts = (int) config('provisioning.health.attempts');
        $delay = (int) config('provisioning.health.delay_seconds');

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                if (Http::timeout(5)->get($url)->successful()) {
                    return true;
                }
            } catch (\Throwable) {
                // Still starting; fall through to the retry.
            }

            sleep($delay);
        }

        return false;
    }

    /**
     * Runs artisan inside the tenant's own container, so tenant credentials
     * stay in that container and are never exported into this process.
     *
     * @param  list<string>  $command
     */
    private function artisan(Tenant $tenant, array $command): void
    {
        $environment = [];
        foreach ($this->tenantEnvironment($tenant) as $key => $value) {
            $environment[] = '--env';
            $environment[] = "{$key}={$value}";
        }

        $this->compose([
            'run', '--rm', '--no-deps',
            ...$environment,
            $tenant->serviceName(),
            'php', 'artisan', ...$command,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tenantEnvironment(Tenant $tenant): array
    {
        return [
            ...$tenant->database->containerEnvironment(),
            'TENANT_INSTITUTION_NAME' => $tenant->name,
            'TENANT_ADMIN_EMAIL' => $tenant->admin_email,
            'TENANT_ADMIN_NAME' => $tenant->admin_name ?? 'Coordinator',
            'TENANT_ADMIN_ROLE' => 'coordinator',
            // Where the shared title registry lives, and this tenant's narrow
            // write token for it.
            'TITLE_REGISTRY_URL' => (string) config('app.url'),
        ];
    }

    private function psql(string $sql): string
    {
        $result = Process::run([
            'psql', (string) config('provisioning.database.admin_dsn'),
            '-v', 'ON_ERROR_STOP=1', '-qtAX', '-c', $sql,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('psql failed: '.$result->errorOutput());
        }

        return $result->output();
    }

    /**
     * @param  list<string>  $arguments
     */
    private function compose(array $arguments): void
    {
        $result = Process::timeout(600)->run([
            'docker', 'compose',
            '-f', (string) config('provisioning.compose_file'),
            ...$arguments,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('docker compose failed: '.$result->errorOutput());
        }
    }
}
