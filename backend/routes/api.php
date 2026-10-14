<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventMemberController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:3,1');
        Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('sessions', [AuthController::class, 'sessions']);
            Route::delete('sessions/{tokenId}', [AuthController::class, 'revokeSession']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::post('change-password', [ProfileController::class, 'changePassword']);
        });
    });

    Route::prefix('events')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('{event}', [EventController::class, 'show']);
        Route::put('{event}', [EventController::class, 'update']);
        Route::delete('{event}', [EventController::class, 'destroy']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('{event}/member-candidates', [EventMemberController::class, 'candidates']);
            Route::get('{event}/members', [EventMemberController::class, 'index']);
            Route::post('{event}/members', [EventMemberController::class, 'store']);
            Route::put('{event}/members/{member}', [EventMemberController::class, 'update']);
            Route::delete('{event}/members/{member}', [EventMemberController::class, 'destroy']);

            Route::get('{event}/tasks', [TaskController::class, 'index']);
            Route::post('{event}/tasks', [TaskController::class, 'store']);
            Route::get('{event}/tasks/{task}', [TaskController::class, 'show']);
            Route::put('{event}/tasks/{task}', [TaskController::class, 'update']);
            Route::delete('{event}/tasks/{task}', [TaskController::class, 'destroy']);

            Route::get('{event}/participants', [ParticipantController::class, 'index']);
            Route::get('{event}/participants/stats', [ParticipantController::class, 'stats']);
            Route::get('{event}/participants/mine', [ParticipantController::class, 'mine']);
            Route::get('{event}/participant-candidates', [ParticipantController::class, 'candidates']);
            Route::post('{event}/participants', [ParticipantController::class, 'store']);
            Route::post('{event}/participants/check-in', [ParticipantController::class, 'checkIn']);
            Route::post('{event}/participants/{participant}/cancel', [ParticipantController::class, 'cancel']);
            Route::delete('{event}/participants/{participant}', [ParticipantController::class, 'destroy']);

            Route::get('{event}/analytics', [AnalyticsController::class, 'overview']);
        });
    });
});
