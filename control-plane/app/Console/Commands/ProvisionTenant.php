<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Provisioning\ProvisioningFailed;
use App\Provisioning\ProvisioningPipeline;
use Illuminate\Console\Command;

final class ProvisionTenant extends Command
{
    protected $signature = 'tenant:provision
                            {slug : Subdomain label, e.g. "tu"}
                            {name : Institution name, e.g. "Tribhuvan University"}
                            {email : Email of the institution\'s first Coordinator}';

    protected $description = 'Provision a tenant: database, role, migrations, bootstrap data, container.';

    public function handle(ProvisioningPipeline $pipeline): int
    {
        $slug = (string) $this->argument('slug');

        try {
            $tenant = $pipeline->provision(
                $slug,
                (string) $this->argument('name'),
                (string) $this->argument('email'),
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (ProvisioningFailed $e) {
            $this->error("Step '{$e->step->value}' failed: {$e->getMessage()}");
            $this->newLine();
            // Every step is idempotent, so the fix is to re-run rather than to
            // unpick whatever was half-created.
            $this->line("Fix the cause and re-run; completed steps are skipped:\n  php artisan tenant:provision {$slug} ...");

            return self::FAILURE;
        }

        $this->info("Tenant '{$tenant->slug}' is active.");
        $this->table(
            ['Property', 'Value'],
            [
                ['URL', 'https://'.$tenant->primaryDomain?->domain],
                ['Status', $tenant->status->label()],
                ['Database', $tenant->database?->database_name],
                ['Coordinator', $tenant->admin_email],
            ],
        );

        $this->newLine();
        $this->warn('The Coordinator password was printed by the bootstrap seeder in the tenant container log.');

        return self::SUCCESS;
    }
}
