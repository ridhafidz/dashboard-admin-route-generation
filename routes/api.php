<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverStatusController;
use App\Http\Controllers\Api\DriverRouteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/driver/check-in', [DriverStatusController::class, 'checkIn']);
    Route::post('/driver/ready', [DriverStatusController::class, 'ready']);
    Route::post('/driver/check-out', [DriverStatusController::class, 'checkOut']);
    Route::get('/driver/routes/today', [DriverRouteController::class, 'today']);
});