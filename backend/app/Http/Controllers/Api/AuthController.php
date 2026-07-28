<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            deviceName: $request->string('device_name', 'web')->toString(),
        );

        return response()->json([
            'message' => 'Login successful.',
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->loadProfile($request->user());

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            user: $request->user(),
            currentPassword: $request->string('current_password')->toString(),
            newPassword: $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.',
        ]);
    }
}
