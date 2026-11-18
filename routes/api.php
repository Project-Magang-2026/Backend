<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RouterController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

// Public Endpoint
Route::post('/login', [AuthController::class, 'login']);

// Protected Endpoints (Laravel Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('routers')->group(function () {
        Route::get('/', [RouterController::class, 'index']);
        Route::get('/{id}/devices', [RouterController::class, 'devices'])->whereNumber('id');
        Route::post('/{id}/block-device', [RouterController::class, 'blockDevice'])->whereNumber('id');
        Route::post('/{id}/wifi', [RouterController::class, 'updateWifi'])->whereNumber('id');
        Route::get('/{id}/diagnostics', [RouterController::class, 'diagnostics'])->whereNumber('id');
        Route::post('/{id}/reboot', [RouterController::class, 'reboot'])->whereNumber('id');
    });

    // Admin Management Endpoints
    Route::middleware(EnsureUserIsAdmin::class)->prefix('admin')->group(function () {
        Route::post('/routers', [AdminController::class, 'storeRouter']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::post('/users/{id}/routers', [AdminController::class, 'syncUserRouters'])->whereNumber('id');
    });
});