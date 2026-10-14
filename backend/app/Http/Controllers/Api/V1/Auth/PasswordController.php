<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        ['email' => $email] = $request->validated();

        $result = $this->authService->sendPasswordResetLink($email);

        if ($result['status'] !== Password::RESET_LINK_SENT) {
            return $this->error('We could not find a user with that email address.', 422);
        }

        $data = $result['reset_url'] !== null ? ['reset_url' => $result['reset_url']] : null;

        return $this->success('Password reset link sent to your email.', $data);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->resetPassword($request->validated());

        if ($result === null) {
            return $this->error('This password reset token is invalid or has expired.', 422);
        }

        $payload = [
            'user' => $result['user'] !== null ? new UserResource($result['user']->load('role')) : null,
            'token' => $result['token'],
        ];

        return $this->success($result['message'], $payload);
    }
}
