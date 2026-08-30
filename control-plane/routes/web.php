<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

/*
 * Control plane — operator-facing only. Institutions never reach this app, and
 * it is the only place holding credentials for every tenant, so everything
 * except the login screen sits behind auth.
 */

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('tenants.index'));

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/new', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant:slug}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant:slug}/retry', [TenantController::class, 'retry'])->name('tenants.retry');
});
