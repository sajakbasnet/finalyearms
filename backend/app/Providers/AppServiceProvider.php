<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePasswordResetLink();
    }

    /**
     * Password reset is completed in the SPA, not a Blade view.
     *
     * The link points at the tenant's own host — the container serving this
     * request already is that tenant, so APP_URL resolves correctly without any
     * tenant lookup.
     */
    private function configurePasswordResetLink(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]);
        });
    }
}
