<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Services\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class RouteControlTowerService
{
    protected const COLORS = [
        '#2563eb', '#16a34a', '#f97316', '#9333ea', '#dc2626',
        '#0891b2', '#ca8a04', '#db2777', '#4f46e5', '#059669',
        '#ea580c', '#7c3aed',
    ];

    public function build(?string $routeDate = null): array
    {
        $branchId = app(BranchContext::class)->getId();
        $routeDate = $this->resolveDate($routeDate, $branchId);

        if (! $branchId || ! $routeDate) {
            return $this->emptyPayload($routeDate);
        }

        $routes = DeliveryRoute::query()
            ->with([
                'branch',
                'driver',
                'vehicle.vehicleType',
                'deliveryStops.store',
            ])
            ->where('branch_id', $branchId)
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->orderBy('id')
            ->get();

        $branch = $routes->first()?->branch;

        $rows = $routes
            ->values()
            ->map(function (DeliveryRoute $route, int $index): array {
                $color = self::COLORS[$index % count(self::COLORS)];
                $stops = $route->deliveryStops
                    ->sortBy('sequence_order')
                    ->values()
                    ->map(function ($stop) use ($route): array {
                        return [
                            'id' => (int) $stop->id,
                            'sequence' => (int) $stop->sequence_order,
                            'status' => $this->enumValue($stop->status),
                            'customer_code' => (string) ($stop->source_customer_code ?: $stop->store?->code ?: ''),
                            'so_numbers' => is_array($stop->source_so_numbers)
                                ? $stop->source_so_numbers
                                : [],
                            'store' => [
                                'id' => $stop->store?->id,
                                'code' => $stop->store?->code,
                                'name' => $stop->store?->name,
                                'address' => $stop->store?->address,
                                'latitude' => $stop->store?->latitude !== null
                                    ? (float) $stop->store->latitude
                                    : null,
                                'longitude' => $stop->store?->longitude !== null
                                    ? (float) $stop->store->longitude
                                    : null,
                            ],
                            'predicted_arrival' => $stop->predicted_arrival_time?->format('H:i'),
                            'service_start' => $stop->predicted_service_start_time?->format('H:i'),
                            'service_end' => $stop->predicted_service_end_time?->format('H:i'),
                            'waiting_minutes' => (int) ($stop->predicted_waiting_minutes ?? 0),
                            'can_act' => $this->canAct($route),
                        ];
                    })
                    ->filter(fn (array $stop): bool =>
                        $stop['store']['latitude'] !== null
                        && $stop['store']['longitude'] !== null
                    )
                    ->values()
                    ->all();

                return [
                    'id' => (int) $route->id,
                    'uuid' => (string) $route->uuid,
                    'color' => $color,
                    'route_date' => $route->route_date?->toDateString(),
                    'source_date' => $route->source_date?->toDateString(),
                    'status' => $this->enumValue($route->status),
                    'approval_status' => (string) ($route->approval_status ?? 'pending'),
                    'driver' => [
                        'id' => $route->driver?->id,
                        'name' => $route->driver?->name ?? '-',
                    ],
                    'vehicle' => [
                        'id' => $route->vehicle?->id,
                        'plate_number' => $route->vehicle?->plate_number ?? '-',
                        'category' => $this->enumValue($route->vehicle?->vehicleType?->category),
                    ],
                    'stop_count' => count($stops),
                    'predicted_duration_minutes' => (int) ($route->predicted_duration_minutes ?? 0),
                    'stops' => $stops,
                    'geometry' => $this->routeGeometry($route, $stops),
                ];
            })
            ->all();

        return [
            'route_date' => $routeDate,
            'branch' => [
                'id' => $branch?->id,
                'code' => $branch?->init_cab,
                'name' => $branch?->name,
                'latitude' => $branch?->latitude !== null ? (float) $branch->latitude : null,
                'longitude' => $branch?->longitude !== null ? (float) $branch->longitude : null,
            ],
            'route_count' => count($rows),
            'stop_count' => collect($rows)->sum('stop_count'),
            'routes' => $rows,
        ];
    }

    public function availableDates(): array
    {
        $branchId = app(BranchContext::class)->getId();

        if (! $branchId) {
            return [];
        }

        return DeliveryRoute::query()
            ->where('branch_id', $branchId)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->selectRaw('DATE(route_date) as route_day')
            ->distinct()
            ->orderByDesc('route_day')
            ->pluck('route_day')
            ->mapWithKeys(fn ($date) => [
                (string) $date => Carbon::parse($date)->format('d-m-Y'),
            ])
            ->all();
    }

    protected function routeGeometry(DeliveryRoute $route, array $stops): array
    {
        $branch = $route->branch;

        if (
            ! $branch
            || $branch->latitude === null
            || $branch->longitude === null
            || empty($stops)
        ) {
            return [];
        }

        $coordinates = collect([
            [(float) $branch->longitude, (float) $branch->latitude],
        ])
            ->concat(
                collect($stops)->map(fn (array $stop) => [
                    (float) $stop['store']['longitude'],
                    (float) $stop['store']['latitude'],
                ])
            )
            ->push([(float) $branch->longitude, (float) $branch->latitude])
            ->all();

        $coordinateString = collect($coordinates)
            ->map(fn (array $point): string => $point[0] . ',' . $point[1])
            ->implode(';');

        $baseUrl = rtrim((string) config(
            'services.osrm.url',
            env('OSRM_URL', 'http://127.0.0.1:5000')
        ), '/');

        try {
            $response = Http::timeout(20)
                ->get(
                    $baseUrl . '/route/v1/driving/' . $coordinateString,
                    [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                    ]
                );

            if ($response->failed() || $response->json('code') !== 'Ok') {
                return $this->fallbackGeometry($coordinates);
            }

            $geometry = $response->json('routes.0.geometry.coordinates');

            if (! is_array($geometry)) {
                return $this->fallbackGeometry($coordinates);
            }

            return collect($geometry)
                ->filter(fn ($point): bool => is_array($point) && count($point) >= 2)
                ->map(fn (array $point): array => [
                    'lat' => (float) $point[1],
                    'lng' => (float) $point[0],
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return $this->fallbackGeometry($coordinates);
        }
    }

    protected function fallbackGeometry(array $coordinates): array
    {
        return collect($coordinates)
            ->map(fn (array $point): array => [
                'lat' => (float) $point[1],
                'lng' => (float) $point[0],
            ])
            ->all();
    }

    protected function canAct(DeliveryRoute $route): bool
    {
        return $this->enumValue($route->status) === RouteStatus::Planned->value
            && ($route->approval_status ?? 'pending') === 'pending';
    }

    protected function resolveDate(?string $routeDate, ?int $branchId): ?string
    {
        if ($routeDate) {
            return Carbon::parse($routeDate)->toDateString();
        }

        if (! $branchId) {
            return null;
        }

        $latest = DeliveryRoute::query()
            ->where('branch_id', $branchId)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->max('route_date');

        return $latest
            ? Carbon::parse($latest)->toDateString()
            : today()->toDateString();
    }

    protected function emptyPayload(?string $routeDate): array
    {
        return [
            'route_date' => $routeDate,
            'branch' => null,
            'route_count' => 0,
            'stop_count' => 0,
            'routes' => [],
        ];
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
