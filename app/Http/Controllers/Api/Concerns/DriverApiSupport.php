<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\DeliveryLog;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait DriverApiSupport
{
    protected function resolveDriver(
        Request $request
    ): Driver {
        $user = $request->user();

        if (! $user) {
            abort(
                401,
                'Unauthenticated.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Token langsung milik Driver
        |--------------------------------------------------------------------------
        */

        if ($user instanceof Driver) {
            return $user;
        }


        /*
        |--------------------------------------------------------------------------
        | Auth user mempunyai driver_id
        |--------------------------------------------------------------------------
        */

        if (
            isset($user->driver_id)
            &&
            $user->driver_id
        ) {
            $driver = Driver::query()
                ->find(
                    $user->driver_id
                );

            if ($driver) {
                return $driver;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Auth user mempunyai relationship driver()
        |--------------------------------------------------------------------------
        */

        if (
            method_exists(
                $user,
                'driver'
            )
        ) {
            $driver = $user->driver;

            if ($driver instanceof Driver) {
                return $driver;
            }
        }


        abort(
            403,
            'Akun ini tidak terhubung dengan Driver.'
        );
    }


    protected function findDriverRoute(
        Driver $driver,
        string $routeUuid
    ): DeliveryRoute {
        $route = DeliveryRoute::query()
            ->where(
                'uuid',
                $routeUuid
            )
            ->where(
                'driver_id',
                $driver->id
            )
            ->whereDate(
                'route_date',
                today()
            )
            ->first();

        if (! $route) {
            abort(
                404,
                'Delivery Route tidak ditemukan.'
            );
        }

        return $route;
    }


    protected function findDriverStop(
        Driver $driver,
        string $stopUuid
    ): DeliveryStop {
        $stop = DeliveryStop::query()
            ->with([
                'deliveryRoute',
                'store',
                'packages.order',
                'packages.product',
            ])
            ->where(
                'uuid',
                $stopUuid
            )
            ->whereHas(
                'deliveryRoute',
                function ($query) use ($driver) {
                    $query
                        ->where(
                            'driver_id',
                            $driver->id
                        )
                        ->whereDate(
                            'route_date',
                            today()
                        );
                }
            )
            ->first();

        if (! $stop) {
            abort(
                404,
                'Delivery Stop tidak ditemukan.'
            );
        }

        return $stop;
    }


    protected function validateLocation(
        Request $request
    ): array {
        return $request->validate([
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
        ]);
    }


    protected function createDeliveryLog(
        DeliveryRoute $route,
        ?DeliveryStop $stop,
        string $eventType,
        float $latitude,
        float $longitude
    ): DeliveryLog {
        $log = new DeliveryLog();

        $log->forceFill([
            'delivery_route_id' =>
                $route->id,

            'delivery_stop_id' =>
                $stop?->id,

            'event_type' =>
                $eventType,

            'latitude' =>
                $latitude,

            'longitude' =>
                $longitude,

            'recorded_at' =>
                now(),
        ]);

        $log->save();

        return $log;
    }


    protected function storeDriverImage(
        UploadedFile $file,
        string $directory,
        string $prefix
    ): string {
        $extension =
            strtolower(
                $file
                    ->getClientOriginalExtension()
            );

        if (! $extension) {
            $extension =
                $file->extension()
                ?: 'jpg';
        }

        $filename =
            $prefix
            . '-'
            . now()->format(
                'Ymd-His'
            )
            . '-'
            . Str::uuid()
            . '.'
            . $extension;

        return $file->storeAs(
            $directory
            . '/'
            . now()->format(
                'Y/m/d'
            ),
            $filename,
            'public'
        );
    }


    protected function enumValue(
        mixed $value
    ): ?string {
        if (
            $value instanceof
            BackedEnum
        ) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}