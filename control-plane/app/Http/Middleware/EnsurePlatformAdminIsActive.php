<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an operator who has been deactivated.
 *
 * `is_active` is checked at login, but a live session would otherwise outlive
 * deactivation until it expired on its own. Checked per request so revoking
 * access takes effect immediately — the same reasoning as
 * EnsureUserIsActive in the tenant app.
 */
final class EnsurePlatformAdminIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::user();

        if ($admin !== null && ! $admin->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
