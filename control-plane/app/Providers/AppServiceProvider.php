<?php

declare(strict_types=1);

namespace App\Providers;

use App\Provisioning\DockerTenantInfrastructure;
use App\Provisioning\TenantInfrastructure;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound to the interface so tests can swap in a fake and exercise the
        // pipeline's ordering, resume and failure handling without Docker.
        $this->app->bind(TenantInfrastructure::class, DockerTenantInfrastructure::class);
    }

    public function boot(): void
    {
        //
    }
}
