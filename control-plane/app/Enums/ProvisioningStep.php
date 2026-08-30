<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The provisioning pipeline, in order.
 *
 * Each step is idempotent so a failed run is resumed by re-running the same
 * command rather than unpicking partial state by hand.
 */
enum ProvisioningStep: string
{
    case CreateDatabase = 'create_database';
    case CreateRole = 'create_role';
    case RunMigrations = 'run_migrations';
    case SeedBootstrap = 'seed_bootstrap';
    case StartContainer = 'start_container';
    case HealthCheck = 'health_check';

    public function label(): string
    {
        return match ($this) {
            self::CreateDatabase => 'Create tenant database',
            self::CreateRole => 'Create scoped database role',
            self::RunMigrations => 'Run migrations',
            self::SeedBootstrap => 'Seed roles, templates and first account',
            self::StartContainer => 'Start tenant container',
            self::HealthCheck => 'Verify the tenant responds',
        };
    }

    /**
     * Steps in execution order.
     *
     * @return list<self>
     */
    public static function pipeline(): array
    {
        return self::cases();
    }
}
