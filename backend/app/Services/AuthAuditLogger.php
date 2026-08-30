<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthEvent;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes the authentication trail.
 *
 * Auditing must never break the thing it observes: if a write fails the request
 * still completes. Failures are surfaced through the application log rather
 * than thrown.
 */
final class AuthAuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        AuthEvent $event,
        ?User $user = null,
        ?string $email = null,
        ?string $reason = null,
        array $context = [],
    ): void {
        try {
            AuthAuditLog::query()->create([
                'user_id' => $user?->id,
                'event' => $event,
                // Lower-cased so a trail can be searched by address regardless
                // of how it was typed at the prompt.
                'email' => $email === null ? null : mb_strtolower($email),
                'succeeded' => ! $event->isFailure(),
                'reason' => $reason,
                'ip_address' => $this->request->ip(),
                'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
                'context' => $context === [] ? null : $context,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
