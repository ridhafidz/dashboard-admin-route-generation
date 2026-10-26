<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStopStatus;
use App\Http\Controllers\Api\Concerns\DriverApiSupport;
use App\Models\DriverLocation;
use App\Http\Controllers\Controller;
use App\Models\DeliveryLog;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\DriverAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverRouteController extends Controller
{
    use DriverApiSupport;


    /*
    |--------------------------------------------------------------------------
    | ROUTE HARI INI
    |--------------------------------------------------------------------------
    */

    public function today(
        Request $request
    ): JsonResponse {
        $driver =
            $this->resolveDriver(
                $request
            );


        $route =
            DeliveryRoute::query()
                ->with([
                    'branch',
                    'vehicle.vehicleType',

                    'deliveryStops' =>
                        fn ($query) =>
                            $query->orderBy(
                                'sequence_order'
                            ),

                    'deliveryStops.store',
                ])
                ->where(
                    'driver_id',
                    $driver->id
                )
                ->whereDate(
                    'route_date',
                    today()
                )
                ->where(
                    'approval_status',
                    'approved'
                )
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                )
                ->latest('id')
                ->first();

        if (! $route) {
            return response()->json([
                'success' => true,

                'message' =>
                    'Tidak ada route hari ini.',

                'data' =>
                    null,
            ]);
        }


        return response()->json([
            'success' => true,

            'data' => [
                'uuid' =>
                    $route->uuid,

                'status' =>
                    $this->enumValue(
                        $route->status
                    ),

                'route_date' =>
                    $route
                        ->route_date
                        ?->format(
                            'Y-m-d'
                        ),

                'branch' => [
                    'code' =>
                        $route
                            ->branch
                            ?->init_cab,

                    'name' =>
                        $route
                            ->branch
                            ?->name,

                    'latitude' =>
                        $route
                            ->branch
                            ?->latitude,

                    'longitude' =>
                        $route
                            ->branch
                            ?->longitude,
                ],

                'vehicle' => [
                    'plate_number' =>
                        $route
                            ->vehicle
                            ?->plate_number,

                    'category' =>
                        $this->enumValue(
                            $route
                                ->vehicle
                                ?->vehicleType
                                ?->category
                        ),

                    'box_type' =>
                        $this->enumValue(
                            $route
                                ->vehicle
                                ?->vehicleType
                                ?->box_type
                        ),
                ],

                'stops' =>
                    $route
                        ->deliveryStops
                        ->map(
                            function ($stop): array {
                                return [
                                    'uuid' =>
                                        $stop->uuid,

                                    'sequence' =>
                                        (int)
                                        $stop
                                            ->sequence_order,

                                    'status' =>
                                        $this->enumValue(
                                            $stop->status
                                        ),

                                    'predicted_arrival_time' =>
                                        $stop
                                            ->predicted_arrival_time
                                            ?->format(
                                                'H:i'
                                            ),

                                    'actual_arrival_time' =>
                                        $stop
                                            ->actual_arrival_time
                                            ?->format(
                                                'Y-m-d H:i:s'
                                            ),

                                    'store' => [
                                        'code' =>
                                            $stop
                                                ->store
                                                ?->code,

                                        'name' =>
                                            $stop
                                                ->store
                                                ?->name,

                                        'address' =>
                                            $stop
                                                ->store
                                                ?->address,

                                        'latitude' =>
                                            $stop
                                                ->store
                                                ?->latitude,

                                        'longitude' =>
                                            $stop
                                                ->store
                                                ?->longitude,
                                    ],

                                    'packages' => [],
                                ];
                            }
                        )
                        ->values(),
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | START ROUTE
    |--------------------------------------------------------------------------
    */

    public function start(
        Request $request,
        string $routeUuid
    ): JsonResponse {
        $validated =
            $this->validateLocation(
                $request
            );

        $driver =
            $this->resolveDriver(
                $request
            );

        $route =
            $this->findDriverRoute(
                $driver,
                $routeUuid
            );
            
        if (
            $route->approval_status !== 'approved'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Route belum disetujui Admin.',
            ], 422);
        }

        $status =
            $this->enumValue(
                $route->status
            );


        if ($status === 'ongoing') {
            return response()->json([
                'success' => true,

                'message' =>
                    'Route sudah berjalan.',
            ]);
        }


        if ($status !== 'planned') {
            return response()->json([
                'success' => false,

                'message' =>
                    "Route berstatus {$status} dan tidak dapat dimulai.",
            ], 422);
        }


        /*
         * Approval gate — route harus sudah disetujui Admin.
         */
        if (
            $route->approval_status
            !==
            'approved'
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Route belum disetujui Admin.',
            ], 422);
        }


        /*
         * Harus sudah check-in.
         */
        $attendance =
            DriverAttendance::query()
                ->where(
                    'driver_id',
                    $driver->id
                )
                ->whereDate(
                    'checkin_at',
                    today()
                )
                ->latest(
                    'checkin_at'
                )
                ->first();


        if (
            ! $attendance
            ||
            $attendance->checkout_at
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Driver belum melakukan check-in aktif.',
            ], 422);
        }


        DB::transaction(
            function () use (
                $driver,
                $route,
                $validated
            ) {
                $route->forceFill([
                    'status' =>
                        'ongoing',
                ]);

                $route->save();


                $driver->forceFill([
                    'status' =>
                        'active',
                ]);

                $driver->save();


                $this->createDeliveryLog(
                    route:
                        $route,

                    stop:
                        null,

                    eventType:
                        'departure',

                    latitude:
                        (float)
                        $validated[
                            'latitude'
                        ],

                    longitude:
                        (float)
                        $validated[
                            'longitude'
                        ],
                );
            }
        );

        DriverLocation::create([
            'driver_id' =>
                $driver->id,

            'delivery_route_id' =>
                $route->id,

            'latitude' =>
                (float)
                $validated['latitude'],

            'longitude' =>
                (float)
                $validated['longitude'],

            'accuracy' =>
                null,

            'speed' =>
                null,

            'heading' =>
                null,

            'recorded_at' =>
                now(),
        ]);


        return response()->json([
            'success' => true,

            'message' =>
                'Route berhasil dimulai.',

            'data' => [
                'route_uuid' =>
                    $route->uuid,

                'status' =>
                    'ongoing',
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | GPS PING
    |--------------------------------------------------------------------------
    */

    public function location(
        Request $request,
        string $routeUuid
    ): JsonResponse {

    /*
    |--------------------------------------------------------------------------
    | VALIDASI GPS
    |--------------------------------------------------------------------------
    */

        $validated =
            $request->validate([

                'latitude' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'longitude' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],

                'accuracy' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'speed' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'heading' => [
                    'nullable',
                    'numeric',
                    'between:0,360',
                ],
            ]);

    /*
    |--------------------------------------------------------------------------
    | DRIVER LOGIN
    |--------------------------------------------------------------------------
    */

        $driver =
            $this->resolveDriver(
                $request
            );

    /*
    |--------------------------------------------------------------------------
    | ROUTE MILIK DRIVER
    |--------------------------------------------------------------------------
    */

        $route =
            $this->findDriverRoute(
                $driver,
                $routeUuid
            );

    /*
    |--------------------------------------------------------------------------
    | HARUS APPROVED
    |--------------------------------------------------------------------------
    */

        if (
            $route->approval_status
            !==
            'approved'
        ) {

            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Route belum di-approve.',
            ], 422);
        }


    /*
    |--------------------------------------------------------------------------
    | HARUS ONGOING
    |--------------------------------------------------------------------------
    */

        if (
            $this->enumValue(
                $route->status
            )
            !==
            'ongoing'
        ) {

            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'GPS hanya diterima ketika route Ongoing.',
            ], 422);
        }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN POSISI
    |--------------------------------------------------------------------------
    */

        $location =
            DriverLocation::create([

                'driver_id' =>
                    $driver->id,

                'delivery_route_id' =>
                    $route->id,

                'latitude' =>
                    (float)
                    $validated[
                        'latitude'
                    ],

                'longitude' =>
                    (float)
                    $validated[
                        'longitude'
                    ],

                'accuracy' =>
                    isset(
                        $validated[
                            'accuracy'
                        ]
                    )
                        ? (float)
                            $validated[
                                'accuracy'
                            ]
                        : null,

                'speed' =>
                    isset(
                        $validated[
                            'speed'
                        ]
                    )
                        ? (float)
                            $validated[
                                'speed'
                            ]
                        : null,

                'heading' =>
                    isset(
                        $validated[
                            'heading'
                        ]
                    )
                        ? (float)
                            $validated[
                                'heading'
                            ]
                        : null,

                'recorded_at' =>
                    now(),
            ]);


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' =>
                true,

            'stored' =>
                true,

            'message' =>
                'Lokasi driver berhasil diperbarui.',

            'data' => [

                'route_uuid' =>
                    $route->uuid,

                'latitude' =>
                    (float)
                    $location->latitude,

                'longitude' =>
                    (float)
                    $location->longitude,

                'accuracy' =>
                    $location->accuracy !== null
                        ? (float)
                            $location->accuracy
                        : null,

                'speed' =>
                    $location->speed !== null
                        ? (float)
                            $location->speed
                        : null,

                'heading' =>
                    $location->heading !== null
                        ? (float)
                            $location->heading
                        : null,

                'recorded_at' =>
                    $location
                        ->recorded_at
                        ?->format(
                            'Y-m-d H:i:s'
                        ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETE ROUTE
    |--------------------------------------------------------------------------
    */

    public function complete(
        Request $request,
        string $routeUuid
    ): JsonResponse {
        $validated =
            $this->validateLocation(
                $request
            );


        $driver =
            $this->resolveDriver(
                $request
            );


        $route =
            $this->findDriverRoute(
                $driver,
                $routeUuid
            );


        $status =
            $this->enumValue(
                $route->status
            );


        if ($status === 'completed') {
            return response()->json([
                'success' => true,

                'message' =>
                    'Route sudah Completed.',
            ]);
        }


        if ($status !== 'ongoing') {
            return response()->json([
                'success' => false,

                'message' =>
                    'Hanya route Ongoing yang dapat diselesaikan.',
            ], 422);
        }


        /*
         * Future-proof:
         * failed nanti dianggap sudah selesai diproses.
         */
        $unfinished =
            DeliveryStop::query()
                ->where(
                    'delivery_route_id',
                    $route->id
                )
                ->whereNotIn(
                    'status',
                    [
                        DeliveryStopStatus::Delivered->value,
                        DeliveryStopStatus::Failed->value,
                    ]
                )
                ->count();


        if ($unfinished > 0) {
            return response()->json([
                'success' => false,

                'message' =>
                    "Masih ada {$unfinished} stop yang belum selesai.",
            ], 422);
        }


        DB::transaction(
            function () use (
                $driver,
                $route,
                $validated
            ) {
                $this->createDeliveryLog(
                    route:
                        $route,

                    stop:
                        null,

                    eventType:
                        'route_completed',

                    latitude:
                        (float)
                        $validated[
                            'latitude'
                        ],

                    longitude:
                        (float)
                        $validated[
                            'longitude'
                        ],
                );


                $route->forceFill([
                    'status' =>
                        'completed',
                ]);

                $route->save();


                /*
                 * Driver selesai bekerja,
                 * siap menerima route lain.
                 */
                $driver->forceFill([
                    'status' =>
                        'ready',
                ]);

                $driver->save();
            }
        );


        return response()->json([
            'success' => true,

            'message' =>
                'Route berhasil diselesaikan.',

            'status' =>
                'completed',
        ]);
    }
}