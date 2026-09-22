<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\DriverApiSupport;
use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use App\Models\DriverAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverStatusController extends Controller
{
    use DriverApiSupport;


    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    |--------------------------------------------------------------------------
    |
    | INACTIVE
    |    ↓
    | ACTIVE
    |
    | Setelah itu driver bisa menekan READY.
    |--------------------------------------------------------------------------
    */

    public function checkIn(
        Request $request
    ): JsonResponse {
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

                'selfie' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
            ]);


        $driver =
            $this->resolveDriver(
                $request
            );


        $existing =
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


        if ($existing) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Driver sudah check-in hari ini.',
            ], 409);
        }


        $photoPath =
            $this->storeDriverImage(
                $request->file(
                    'selfie'
                ),
                'driver-attendances/checkin',
                'checkin'
            );


        $attendance =
            DB::transaction(
                function () use (
                    $driver,
                    $validated,
                    $photoPath
                ) {
                    $attendance =
                        new DriverAttendance();

                    $attendance->forceFill([
                        'driver_id' =>
                            $driver->id,

                        'checkin_at' =>
                            now(),

                        'checkin_latitude' =>
                            $validated[
                                'latitude'
                            ],

                        'checkin_longitude' =>
                            $validated[
                                'longitude'
                            ],

                        'checkin_photo' =>
                            $photoPath,
                    ]);

                    $attendance->save();


                    /*
                     * Driver sudah hadir,
                     * tetapi belum menyatakan READY.
                     */
                    $driver->forceFill([
                        'status' =>
                            'active',
                    ]);

                    $driver->save();


                    return $attendance;
                }
            );


        return response()->json([
            'success' => true,

            'message' =>
                'Check-in berhasil.',

            'data' => [
                'attendance_id' =>
                    $attendance->uuid
                    ?? $attendance->id,

                'status' =>
                    'active',

                'checkin_at' =>
                    $attendance
                        ->checkin_at
                        ?->format(
                            'Y-m-d H:i:s'
                        ),

                'latitude' =>
                    (float)
                    $attendance
                        ->checkin_latitude,

                'longitude' =>
                    (float)
                    $attendance
                        ->checkin_longitude,

                'photo' =>
                    $attendance
                        ->checkin_photo,
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | READY
    |--------------------------------------------------------------------------
    |
    | Driver sudah check-in dan siap menerima assignment.
    |--------------------------------------------------------------------------
    */

    public function ready(
        Request $request
    ): JsonResponse {
        $driver =
            $this->resolveDriver(
                $request
            );


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


        if (! $attendance) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Driver harus check-in terlebih dahulu.',
            ], 422);
        }


        if ($attendance->checkout_at) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Driver sudah check-out hari ini.',
            ], 422);
        }


        $ongoingRoute =
            DeliveryRoute::query()
                ->where(
                    'driver_id',
                    $driver->id
                )
                ->whereDate(
                    'route_date',
                    today()
                )
                ->where(
                    'status',
                    'ongoing'
                )
                ->exists();


        if ($ongoingRoute) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Driver sedang menjalankan route.',
            ], 422);
        }


        $driver->forceFill([
            'status' =>
                'ready',
        ]);

        $driver->save();


        return response()->json([
            'success' => true,

            'message' =>
                'Driver sekarang READY.',

            'status' =>
                'ready',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK OUT
    |--------------------------------------------------------------------------
    */

    public function checkOut(
        Request $request
    ): JsonResponse {
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

                'selfie' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
            ]);


        $driver =
            $this->resolveDriver(
                $request
            );


        /*
         * Route Planned maupun Ongoing
         * masih menjadi tanggung jawab driver.
         */
        $activeRoute =
            DeliveryRoute::query()
                ->where(
                    'driver_id',
                    $driver->id
                )
                ->whereDate(
                    'route_date',
                    today()
                )
                ->whereIn(
                    'status',
                    [
                        'planned',
                        'ongoing',
                    ]
                )
                ->exists();


        if ($activeRoute) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Tidak dapat check-out karena masih memiliki route Planned/Ongoing.',
            ], 422);
        }


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


        if (! $attendance) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Check-in hari ini tidak ditemukan.',
            ], 422);
        }


        if ($attendance->checkout_at) {
            return response()->json([
                'success' => true,

                'message' =>
                    'Driver sudah check-out.',
            ]);
        }


        $photoPath =
            $this->storeDriverImage(
                $request->file(
                    'selfie'
                ),
                'driver-attendances/checkout',
                'checkout'
            );


        DB::transaction(
            function () use (
                $attendance,
                $driver,
                $validated,
                $photoPath
            ) {
                $attendance->forceFill([
                    'checkout_at' =>
                        now(),

                    'checkout_latitude' =>
                        $validated[
                            'latitude'
                        ],

                    'checkout_longitude' =>
                        $validated[
                            'longitude'
                        ],

                    'checkout_photo' =>
                        $photoPath,
                ]);

                $attendance->save();


                $driver->forceFill([
                    'status' =>
                        'inactive',
                ]);

                $driver->save();
            }
        );


        return response()->json([
            'success' => true,

            'message' =>
                'Check-out berhasil.',

            'status' =>
                'inactive',
        ]);
    }
}