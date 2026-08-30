<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ControlPlaneAuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Records operator actions.
 *
 * Auditing must never break the action it observes, so a failed write is
 * reported rather than thrown.
 */
final class ControlPlaneAudit
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $detail
     */
    public function record(string $action, ?Tenant $tenant = null, array $detail = []): void
    {
        try {
            ControlPlaneAuditLog::query()->create([
                'platform_admin_id' => Auth::id(),
                'tenant_id' => $tenant?->id,
                'action' => $action,
                'detail' => $detail === [] ? null : $detail,
                'ip_address' => $this->request->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
