<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverRouteController;
use App\Http\Controllers\Api\DriverStatusController;
use App\Http\Controllers\Api\DriverStopController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

Route::post(
    '/login',
    [
        AuthController::class,
        'login',
    ]
);

Route::get(
    '/performance-test',
    function () {
        return response()->json([
            'status' =>
                'ok',

            'time' =>
                now()
                    ->toDateTimeString(),
        ]);
    }
);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED
|--------------------------------------------------------------------------
*/

Route::middleware(
    'auth:sanctum'
)
->group(
    function () {

        /*
        |--------------------------------------------------------------------------
        | LOGOUT
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout',
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | DRIVER
        |--------------------------------------------------------------------------
        */

        Route::prefix(
            'driver'
        )
        ->group(
            function () {

                /*
                |--------------------------------------------------------------------------
                | STATUS / ATTENDANCE
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/check-in',
                    [
                        DriverStatusController::class,
                        'checkIn',
                    ]
                );


                Route::post(
                    '/ready',
                    [
                        DriverStatusController::class,
                        'ready',
                    ]
                );


                Route::post(
                    '/check-out',
                    [
                        DriverStatusController::class,
                        'checkOut',
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | ROUTE HARI INI
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/routes/today',
                    [
                        DriverRouteController::class,
                        'today',
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | ROUTE EXECUTION
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/routes/{routeUuid}/start',
                    [
                        DriverRouteController::class,
                        'start',
                    ]
                );


                Route::post(
                    '/routes/{routeUuid}/location',
                    [
                        DriverRouteController::class,
                        'location',
                    ]
                );


                Route::post(
                    '/routes/{routeUuid}/complete',
                    [
                        DriverRouteController::class,
                        'complete',
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | DELIVERY STOP
                |--------------------------------------------------------------------------
                */

                Route::post(
                    '/stops/{stopUuid}/arrive',
                    [
                        DriverStopController::class,
                        'arrive',
                    ]
                );


                Route::post(
                    '/stops/{stopUuid}/complete',
                    [
                        DriverStopController::class,
                        'complete',
                    ]
                );
            }
        );
    }
);