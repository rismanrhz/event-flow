<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success('Registration successful.', [
            'user' => new UserResource($result['user']->load('role')),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), (bool) $request->boolean('remember'));

        if ($result === null) {
            return $this->error('Invalid email or password.', 401);
        }

        return $this->success('Login successful.', [
            'user' => new UserResource($result['user']->load('role')),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return $this->success('Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success('Current user.', new UserResource($user->load('role')));
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at']);

        return $this->success('Active sessions.', $sessions);
    }

    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->tokens()->find($tokenId);

        if ($token === null) {
            return $this->error('Session not found.', 404);
        }

        if ($user->currentAccessToken() !== null && $token->id === $user->currentAccessToken()->id) {
            return $this->error('You cannot revoke the current session.', 422);
        }

        $this->authService->logout($user, $token->id);

        return $this->success('Session revoked.');
    }
}
