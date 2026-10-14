<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->is('api/*')) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422),
            $e instanceof AuthenticationException => $this->apiError('Unauthenticated.', 401),
            $e instanceof AuthorizationException => $this->apiError('You are not authorized to perform this action.', 403),
            $e instanceof NotFoundHttpException => $this->apiError('Resource not found.', 404),
            $e instanceof ThrottleRequestsException => $this->apiError('Too many requests. Please try again later.', 429),
            // Requests without an Accept: application/json header hit the auth-guard's
            // "redirect to login" path, which throws because there is no web `login` route.
            $e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException => $this->apiError('Unauthenticated.', 401),
            $e instanceof HttpException => $this->apiError(
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                $e->getStatusCode() > 0 ? $e->getStatusCode() : 400
            ),
            default => $this->apiError(
                config('app.debug') ? $e->getMessage() : 'An unexpected server error occurred.',
                500
            ),
        };
    }

    private function apiError(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
