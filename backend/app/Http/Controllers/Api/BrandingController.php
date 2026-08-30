<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Services\BrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class BrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $brandingService,
    ) {}

    /**
     * Public by design: the SPA renders the login screen themed, before any
     * token exists. Exposes presentation only — no tenant internals.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->brandingService->current()->toBrandingPayload(),
        ]);
    }

    /** Restricted to holders of `branding.manage` by the route's `can:` middleware. */
    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $logo = $request->file('logo');
        $favicon = $request->file('favicon');

        $branding = $this->brandingService->update(
            $request->safe()->except(['logo', 'favicon']),
            $logo instanceof UploadedFile ? $logo : null,
            $favicon instanceof UploadedFile ? $favicon : null,
        );

        return response()->json([
            'message' => 'Branding updated successfully.',
            'data' => $branding->toBrandingPayload(),
        ]);
    }
}
