<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use App\Models\DriverLocation;
use App\Services\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->getId();

        $routeDate = $request->string('route_date')->toString();

        if (! $branchId || ! $routeDate) {
            return response()->json([
                'drivers' => [],
            ]);
        }

        $routes = DeliveryRoute::query()
            ->with([
                'driver',
                'vehicle',
            ])
            ->where('branch_id', $branchId)
            ->whereDate('route_date', $routeDate)
            ->where('approval_status', 'approved')
            ->where('status', 'ongoing')
            ->get();

        $driverIds = $routes
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->values();

        $latestLocations = DriverLocation::query()
            ->whereIn('driver_id', $driverIds)
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('driver_id')
            ->keyBy('driver_id');

        $drivers = $routes
            ->map(function ($route) use ($latestLocations) {

                $location =
                    $latestLocations->get($route->driver_id);

                if (! $location) {
                    return null;
                }

                return [
                    'driver_id' =>
                        (int) $route->driver_id,

                    'route_id' =>
                        (int) $route->id,

                    'driver_name' =>
                        $route->driver?->name ?? '-',

                    'vehicle_plate' =>
                        $route->vehicle?->plate_number ?? '-',

                    'latitude' =>
                        (float) $location->latitude,

                    'longitude' =>
                        (float) $location->longitude,

                    'accuracy' =>
                        $location->accuracy !== null
                            ? (float) $location->accuracy
                            : null,

                    'speed' =>
                        $location->speed !== null
                            ? (float) $location->speed
                            : null,

                    'heading' =>
                        $location->heading !== null
                            ? (float) $location->heading
                            : null,

                    'recorded_at' =>
                        $location->recorded_at?->toDateTimeString(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'drivers' => $drivers,
        ]);
    }
}