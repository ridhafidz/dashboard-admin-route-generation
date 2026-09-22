<?php

namespace App\Http\Controllers\Api;

use App\Enums\PackageStatus;
use App\Enums\DeliveryStopStatus;
use App\Http\Controllers\Api\Concerns\DriverApiSupport;
use App\Http\Controllers\Controller;
use App\Models\DeliveryLog;
use App\Models\DeliveryStop;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverStopController extends Controller
{
    use DriverApiSupport;


    /*
    |--------------------------------------------------------------------------
    | ARRIVE
    |--------------------------------------------------------------------------
    */

    public function arrive(
        Request $request,
        string $stopUuid
    ): JsonResponse {
        $validated =
            $this->validateLocation(
                $request
            );


        $driver =
            $this->resolveDriver(
                $request
            );


        $stop =
            $this->findDriverStop(
                $driver,
                $stopUuid
            );


        $route =
            $stop->deliveryRoute;


        if (
            $this->enumValue(
                $route->status
            )
            !==
            'ongoing'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Route belum Ongoing.',
            ], 422);
        }


        if (
            $stop->status
            ===
            DeliveryStopStatus::Completed
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Stop ini sudah selesai diproses.',
            ], 422);
        }


        /*
        |--------------------------------------------------------------------------
        | JANGAN LOMPAT STOP
        |--------------------------------------------------------------------------
        */

        $previousUnfinished =
            DeliveryStop::query()
                ->where(
                    'delivery_route_id',
                    $route->id
                )
                ->where(
                    'sequence_order',
                    '<',
                    $stop->sequence_order
                )
                ->whereNotIn(
                    'status',
                    [
                        DeliveryStopStatus::Completed->value,
                        DeliveryStopStatus::Failed->value,
                    ]
                )
                ->exists();


        if ($previousUnfinished) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Stop sebelumnya belum diselesaikan.',
            ], 422);
        }


        DB::transaction(
            function () use (
                $stop,
                $route,
                $validated
            ) {
                if (
                    ! $stop
                        ->actual_arrival_time
                ) {
                    $stop->forceFill([
                        'actual_arrival_time' =>
                            now(),
                    ]);

                    $stop->save();
                }


                /*
                 * Arrival hanya sekali per stop.
                 */
                $alreadyLogged =
                    DeliveryLog::query()
                        ->where(
                            'delivery_route_id',
                            $route->id
                        )
                        ->where(
                            'delivery_stop_id',
                            $stop->id
                        )
                        ->where(
                            'event_type',
                            'arrival'
                        )
                        ->exists();


                if (! $alreadyLogged) {
                    $this->createDeliveryLog(
                        route:
                            $route,

                        stop:
                            $stop,

                        eventType:
                            'arrival',

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
            }
        );


        $stop->refresh();


        return response()->json([
            'success' => true,

            'message' =>
                'Arrival berhasil dicatat.',

            'data' => [
                'stop_uuid' =>
                    $stop->uuid,

                'store_name' =>
                    $stop
                        ->store
                        ?->name,

                'actual_arrival_time' =>
                    $stop
                        ->actual_arrival_time
                        ?->format(
                            'Y-m-d H:i:s'
                        ),
            ],
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | COMPLETE DELIVERY
    |--------------------------------------------------------------------------
    */

    public function complete(
        Request $request,
        string $stopUuid
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

                'proof_photo' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
            ]);


        $driver =
            $this->resolveDriver(
                $request
            );


        $stop =
            $this->findDriverStop(
                $driver,
                $stopUuid
            );


        $route =
            $stop->deliveryRoute;


        if (
            $this->enumValue(
                $route->status
            )
            !==
            'ongoing'
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Route tidak sedang berjalan.',
            ], 422);
        }


        if (
            $stop->status
            ===
            DeliveryStopStatus::Completed
        ) {
            return response()->json([
                'success' => true,

                'message' =>
                    'Stop sudah Delivered.',
            ]);
        }


        if (
            ! $stop
                ->actual_arrival_time
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Tekan Arrive terlebih dahulu sebelum Complete Delivery.',
            ], 422);
        }


        $photoPath =
            $this->storeDriverImage(
                $request->file(
                    'proof_photo'
                ),
                'delivery-proofs',
                'delivery'
            );


        DB::transaction(
            function () use (
                $stop,
                $route,
                $validated,
                $photoPath
            ) {
                /*
                |--------------------------------------------------------------------------
                | DELIVERY STOP
                |--------------------------------------------------------------------------
                */

                $stop->forceFill([
                    'status' =>
                        DeliveryStopStatus::Completed,

                    'proof_of_delivery' =>
                        $photoPath,
                ]);

                $stop->save();


                /*
                |--------------------------------------------------------------------------
                | PACKAGES
                |--------------------------------------------------------------------------
                */

                $packages =
                    $stop
                        ->packages()
                        ->get();


                foreach (
                    $packages
                    as
                    $package
                ) {
                    $package->forceFill([
                        'status' =>
                            PackageStatus::Delivered,
                    ]);

                    $package->save();
                }


                /*
                |--------------------------------------------------------------------------
                | SALES ORDERS
                |--------------------------------------------------------------------------
                */

                $orderIds =
                    $packages
                        ->pluck(
                            'order_id'
                        )
                        ->filter()
                        ->unique();


                foreach (
                    $orderIds
                    as
                    $orderId
                ) {
                    $order =
                        Order::query()
                            ->find(
                                $orderId
                            );

                    if (! $order) {
                        continue;
                    }


                    $stillOpen =
                        $order
                            ->packages()
                            ->where(
                                'status',
                                '!=',
                                PackageStatus::Delivered->value
                            )
                            ->exists();


                    $order->forceFill([
                        'status' =>
                            $stillOpen
                                ? 'processing'
                                : 'completed',
                    ]);

                    $order->save();
                }


                /*
                |--------------------------------------------------------------------------
                | TRACKING EVENT
                |--------------------------------------------------------------------------
                */

                $alreadyLogged =
                    DeliveryLog::query()
                        ->where(
                            'delivery_route_id',
                            $route->id
                        )
                        ->where(
                            'delivery_stop_id',
                            $stop->id
                        )
                        ->where(
                            'event_type',
                            'delivered'
                        )
                        ->exists();


                if (! $alreadyLogged) {
                    $this->createDeliveryLog(
                        route:
                            $route,

                        stop:
                            $stop,

                        eventType:
                            'delivered',

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
            }
        );


        return response()->json([
            'success' => true,

            'message' =>
                'Delivery berhasil diselesaikan.',

            'data' => [
                'stop_uuid' =>
                    $stop->uuid,

                'store_name' =>
                    $stop
                        ->store
                        ?->name,

                'status' =>
                    $this->enumValue(
                        $stop->fresh()->status
                    ),

                'proof_photo' =>
                    $photoPath,
            ],
        ]);
    }
}