<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a tenant by its registry token.
 *
 * Deliberately narrow: this identifies which institution is contributing a
 * title and nothing more. It grants no database access and no reach into the
 * tenant registry, so the rule that a tenant container never holds
 * control-plane credentials still holds.
 *
 * Tokens are compared by hash, and with hash_equals so a wrong token cannot be
 * narrowed down by timing.
 */
final class AuthenticateRegistryToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Registry-Token');

        if (! is_string($token) || $token === '') {
            return response()->json([
                'message' => 'A registry token is required to contribute a title.',
            ], 401);
        }

        $hash = hash('sha256', $token);

        // Fetch by hash, then re-compare in constant time: the index lookup
        // finds the row, hash_equals confirms it without leaking timing.
        $tenant = Tenant::query()->where('registry_token_hash', $hash)->first();

        if ($tenant === null || ! hash_equals((string) $tenant->registry_token_hash, $hash)) {
            return response()->json(['message' => 'That registry token is not valid.'], 401);
        }

        // Attached for the controller; never exposed in a response.
        $request->attributes->set('registry_tenant', $tenant);

        return $next($request);
    }
}
