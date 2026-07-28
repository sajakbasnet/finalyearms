<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->forUser($request->user()),
        ]);
    }
}
