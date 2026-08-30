<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated requests from deactivated accounts.
 *
 * Without this, `is_active` is only consulted at login, so deactivating a
 * departed or compromised user leaves every token they already hold working
 * until someone deletes it by hand. Checked per request so deactivation takes
 * effect immediately, however it was performed.
 */
final class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            // Revoke on the way out, so a deactivated account stops costing a
            // database lookup on every subsequent request.
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Your account has been deactivated. Contact the administrator.',
            ], 403);
        }

        return $next($request);
    }
}
