<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DriverLocationController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth',
])
    ->prefix(
        'admin/delivery-routes'
    )
    ->group(function () {

        Route::get(
            '/driver-locations',
            [
                DriverLocationController::class,
                'index',
            ]
        )
            ->name(
                'admin.delivery-routes.driver-locations'
            );
    });

Route::get('/performance-test', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString(),
    ]);
});