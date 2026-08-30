<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TitleRegistryController;
use Illuminate\Support\Facades\Route;

/*
 * The public face of the control plane: the cross-institution project title
 * registry.
 *
 * This is the only thing here that is not operator-only, and deliberately so —
 * a student anywhere should be able to find out whether their topic has already
 * been done, and requiring credentials would defeat that.
 *
 * Nothing else about a tenant is reachable from these routes.
 */
Route::prefix('titles')->group(function (): void {
    // Public and read-only. Throttled because it is unauthenticated.
    Route::get('/check', [TitleRegistryController::class, 'check'])->middleware('throttle:60,1');
    Route::get('/stats', [TitleRegistryController::class, 'stats'])->middleware('throttle:30,1');

    // Contributing requires a per-tenant registry token, which grants nothing
    // beyond appending that tenant's own approved titles.
    Route::post('/', [TitleRegistryController::class, 'store'])
        ->middleware(['registry.token', 'throttle:120,1']);
});
