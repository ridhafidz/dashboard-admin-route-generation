<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRoute;
use Illuminate\Http\Request;

class DriverRouteController extends Controller
{
    public function today(Request $request)
    {
        $driver = $request->user()->driver;

        if (!$driver) {
            return response()->json(['message' => 'Akun ini tidak terhubung ke data driver'], 404);
        }

        $route = DeliveryRoute::query()
            ->with([
                'branch',
                'vehicle.vehicleType',
                'deliveryStops.store',
                'deliveryStops.packages',
            ])
            ->where('driver_id', $driver->id)
            ->whereDate('route_date', today())
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->first();

        if (!$route) {
            return response()->json(['message' => 'Belum ada rute untuk hari ini'], 404);
        }

        return response()->json([
            'route_id' => $route->uuid,
            'route_date' => $route->route_date?->toDateString(),
            'status' => $route->status,
            'predicted_duration_minutes' => $route->predicted_duration_minutes,
            'predicted_package_count' => $route->predicted_package_count,
            'started_at' => $route->started_at,
            'completed_at' => $route->completed_at,
            'branch' => $route->branch ? [
                'id' => $route->branch->uuid,
                'name' => $route->branch->name,
                'latitude' => (float) $route->branch->latitude,
                'longitude' => (float) $route->branch->longitude,
                'start_time' => $route->branch->start_time,
            ] : null,
            'vehicle' => $route->vehicle ? [
                'id' => $route->vehicle->uuid,
                'plate_number' => $route->vehicle->plate_number,
                'category' => $route->vehicle->vehicleType?->category,
                'box_type' => $route->vehicle->vehicleType?->box_type,
            ] : null,
            'stops' => $route->deliveryStops->map(fn ($stop) => [
                'id' => $stop->uuid,
                'sequence_order' => $stop->sequence_order,
                'status' => $stop->status,
                'predicted_arrival_time' => $stop->predicted_arrival_time,
                'predicted_service_start_time' => $stop->predicted_service_start_time,
                'predicted_service_end_time' => $stop->predicted_service_end_time,
                'predicted_waiting_minutes' => $stop->predicted_waiting_minutes,
                'actual_arrival_time' => $stop->actual_arrival_time,
                'actual_departure_time' => $stop->actual_departure_time,
                'store' => [
                    'id' => $stop->store->uuid,
                    'name' => $stop->store->name,
                    'code' => $stop->store->code,
                    'address' => $stop->store->address,
                    'latitude' => (float) $stop->store->latitude,
                    'longitude' => (float) $stop->store->longitude,
                    'opening_time' => $stop->store->opening_time,
                    'closing_time' => $stop->store->closing_time,
                ],
                'packages' => $stop->packages->map(fn ($package) => [
                    'id' => $package->uuid,
                    'tracking_number' => $package->tracking_number,
                    'item' => $package->item,
                    'quantity' => $package->quantity,
                    'weight_kg' => (float) $package->weight_kg,
                    'volume_m3' => (float) ($package->volume_m3 ?? 0),
                    'status' => $package->status,
                ])->values(),
            ])->values(),
        ]);
    }
}
