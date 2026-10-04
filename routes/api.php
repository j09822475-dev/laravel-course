<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\SessionApiController;
use App\Http\Middleware\ResolveApiTrainee;
use Illuminate\Support\Facades\Route;

/*
 * API для мобильного клиента. Аутентификация — персональные токены Sanctum:
 * токен выдаётся на устройство и отзывается по отдельности.
 */

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', ResolveApiTrainee::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('me', [ProfileApiController::class, 'show']);
        Route::put('me', [ProfileApiController::class, 'update']);
        Route::get('progress', [ProfileApiController::class, 'progress']);
        Route::post('share', [ProfileApiController::class, 'share']);
        Route::delete('share', [ProfileApiController::class, 'unshare']);

        Route::get('sessions', [SessionApiController::class, 'index']);
        Route::post('sessions', [SessionApiController::class, 'store']);
        Route::get('sessions/{session}', [SessionApiController::class, 'show']);
        Route::post('sessions/{session}/answer', [SessionApiController::class, 'answer']);
        Route::get('sessions/{session}/report', [SessionApiController::class, 'report']);
    });
});
