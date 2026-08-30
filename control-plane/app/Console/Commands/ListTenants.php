<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

final class ListTenants extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'List tenants with their status, schema version and health.';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->with(['primaryDomain', 'deployment'])
            ->orderBy('slug')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants provisioned yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Name', 'Status', 'Domain', 'Image', 'Schema', 'Health'],
            $tenants->map(fn (Tenant $tenant): array => [
                $tenant->slug,
                $tenant->name,
                $tenant->status->value,
                $tenant->primaryDomain?->domain ?? '—',
                $tenant->deployment?->image_tag ?? '—',
                $tenant->deployment?->schema_version ?? '—',
                $tenant->deployment?->health ?? 'unknown',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
