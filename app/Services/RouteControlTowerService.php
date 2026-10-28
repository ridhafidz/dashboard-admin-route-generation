<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
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
                            'box_type' => trim((string) ($stop->box_type ?? '')),
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

                $routeMetrics = $this->routeMetrics($route, $stops);
                $volumeMetrics = $this->routeVolumeMetrics($route, $stops);
                $routeTimeline =
                    $this->routeTimeline(
                        $route,
                        $stops,
                        $routeMetrics
                    );

                return [
                    'id' => (int) $route->id,
                    'uuid' => (string) $route->uuid,
                    'color' => $color,

                    'route_date' => $route->route_date?->toDateString(),
                    'source_date' => $route->source_date?->toDateString(),

                    'status' => $this->enumValue($route->status),

                    'approval_status' =>
                        (string) ($route->approval_status ?? 'pending'),

                    'driver' => [
                        'id' => $route->driver?->id,
                        'name' => $route->driver?->name ?? '-',
                    ],

                    'vehicle' => [
                        'id' => $route->vehicle?->id,
                        'plate_number' => $route->vehicle?->plate_number ?? '-',
                        'category' => $this->enumValue(
                            $route->vehicle?->vehicleType?->category
                        ),
                        'box_type' => $this->enumValue(
                            $route->vehicle?->vehicleType?->box_type
                        ),
                    ],

                    'volume' => $volumeMetrics,

                    /*
                    |--------------------------------------------------------------------------
                    | ROUTE SUMMARY
                    |--------------------------------------------------------------------------
                    */

                    'stop_count' => count($stops),

                    'predicted_duration_minutes' =>
                        (int) ($route->predicted_duration_minutes ?? 0),

                    'predicted_cost' =>
                        (float) ($route->predicted_cost ?? 0),

                    'predicted_package_count' =>
                        (int) ($route->predicted_package_count ?? 0),

                    'start_time' =>
                        $routeTimeline['start_time'],

                    'finish_time' =>
                        $routeTimeline['finish_time'],

                    /*
                    |--------------------------------------------------------------------------
                    | OSRM METRICS
                    |--------------------------------------------------------------------------
                    */

                    'distance_km' =>
                        $routeMetrics['distance_km'],

                    'travel_duration_minutes' =>
                        $routeMetrics['travel_duration_minutes'],

                    'geometry' =>
                        $routeMetrics['geometry'],

                    'stops' => $stops,
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

    protected function routeVolumeMetrics(
        DeliveryRoute $route,
        array $stops
    ): array {

        $vehicleType =
            $route->vehicle?->vehicleType;
        
        $volumeM3 = (float) (
            $vehicleType?->volume_m3
            ?? 0
        );

        $boxCapacityCm3 =
            $volumeM3 * 1_000_000;

        $packingUtilization = (float) (
            $vehicleType?->packing_utilization
            ?? 0
        );

       if (
            $packingUtilization <= 0
            || $packingUtilization > 1
        ) {
            $packingUtilization = 1;
        }

        $usableCapacityCm3 = $boxCapacityCm3 * $packingUtilization;

        $soBoxKeys = collect($stops)
            ->flatMap(function (array $stop): array {

                $boxType = trim(
                    (string) (
                        $stop['box_type']
                        ?? ''
                    )
                );

                if ($boxType === '') {
                    return [];
                }

                return collect(
                    $stop['so_numbers'] ?? []
                )
                    ->map(function ($soNumber) use ($boxType): array {

                        $soNumber = trim(
                            (string) $soNumber
                        );

                        if ($soNumber === '') {
                            return [];
                        }

                        return [
                            'so_number' =>
                                $soNumber,

                            'box_type' =>
                                $boxType,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->filter()
            ->unique(
                fn (array $item): string =>
                    $item['so_number']
                    . '::'
                    . $item['box_type']
            )
            ->values();

        if ($soBoxKeys->isEmpty()) {
            return [
                'loaded_cm3' => 0,

                'box_capacity_cm3' =>
                    round($boxCapacityCm3, 2),

                'packing_utilization' =>
                    round(
                        $packingUtilization,
                        4
                    ),

                'usable_capacity_cm3' =>
                    round(
                        $usableCapacityCm3,
                        2
                    ),

                'remaining_cm3' =>
                    round(
                        $usableCapacityCm3,
                        2
                    ),

                'utilization_pct' => 0,
            ];
        }

        $soNumbers = $soBoxKeys
            ->pluck('so_number')
            ->unique()
            ->values()
            ->all();

        $connection = config(
            'services.delivery_source.connection'
        );

        $db =
            is_string($connection)
            && trim($connection) !== ''
                ? DB::connection(
                    trim($connection)
                )
                : DB::connection();

        $soTable = trim(
            (string) config(
                'services.delivery_source.so_table',
                'so_sda'
            )
        );

        if ($soTable === '') {
            $soTable = 'so_sda';
        }

        $sourceRows = $db
            ->table($soTable)
            ->whereIn(
                'so_number',
                $soNumbers
            )
            ->select([
                'so_number',
                'product_id',
                'qty_cartons',
                'qty_packs',
                'qty_pcs',
            ])
            ->get();

        $productCodes = $sourceRows
            ->pluck('product_id')
            ->map(
                fn ($value): string =>
                    trim((string) $value)
            )
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn(
                'code',
                $productCodes
            )
            ->get()
            ->keyBy(
                fn (Product $product): string =>
                    trim(
                        (string) $product->code
                    )
            );

        $allowedKeys = $soBoxKeys
            ->mapWithKeys(
                fn (array $item): array => [
                    $item['so_number']
                    . '::'
                    . $item['box_type']
                        => true,
                ]
            );

        $loadedVolumeCm3 = 0.0;

        foreach ($sourceRows as $row) {

            $productCode = trim(
                (string) $row->product_id
            );

            /** @var Product|null $product */
            $product = $products->get($productCode);
            if (! $product) {
                continue;
            }

            $boxType = trim(
                (string) $product->box_type
            );

            $soNumber = trim(
                (string) $row->so_number
            );

            $key = $soNumber . '::' . $boxType;

            if (! isset($allowedKeys[$key])) {
                continue;
            }

            $qtyCartons =
                is_numeric(
                    $row->qty_cartons
                    ?? null
                )
                    ? (float) $row->qty_cartons
                    : 0;

            $qtyPacks =
                is_numeric(
                    $row->qty_packs
                    ?? null
                )
                    ? (float) $row->qty_packs
                    : 0;

            $qtyPcs =
                is_numeric(
                    $row->qty_pcs
                    ?? null
                )
                    ? (float) $row->qty_pcs
                    : 0;

            $cartonVolumeCm3 =
                (float) (
                    $product->carton_volume_cm3
                    ?? 0
                );

            $unitVolumeCm3 =
                (float) (
                    $product->unit_volume_cm3
                    ?? 0
                );

            $unitsPerPack =
                (int) (
                    $product->units_per_pack
                    ?? 0
                );

            $itemVolumeCm3 =
                (
                    $qtyCartons
                    * $cartonVolumeCm3
                )
                +
                (
                    $qtyPacks
                    * $unitsPerPack
                    * $unitVolumeCm3
                )
                +
                (
                    $qtyPcs
                    * $unitVolumeCm3
                );

            $loadedVolumeCm3 += $itemVolumeCm3;
        }

        $remainingCm3 = max(
            0,
            $usableCapacityCm3
            - $loadedVolumeCm3
        );

        $utilizationPct =
            $usableCapacityCm3 > 0
                ? (
                    $loadedVolumeCm3
                    /
                    $usableCapacityCm3
                ) * 100
                : 0;

        return [
            'loaded_cm3' =>
                round(
                    $loadedVolumeCm3,
                    2
                ),

            'box_capacity_cm3' =>
                round(
                    $boxCapacityCm3,
                    2
                ),

            'packing_utilization' =>
                round(
                    $packingUtilization,
                    4
                ),

            'usable_capacity_cm3' =>
                round(
                    $usableCapacityCm3,
                    2
                ),

            'remaining_cm3' =>
                round(
                    $remainingCm3,
                    2
                ),

            'utilization_pct' =>
                round(
                    $utilizationPct,
                    2
                ),
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

    protected function routeMetrics(
        DeliveryRoute $route,
        array $stops
    ): array {

        $branch = $route->branch;

        if (
            ! $branch
            || $branch->latitude === null
            || $branch->longitude === null
            || empty($stops)
        ) {
            return [
                'geometry' => [],
                'distance_km' => 0,
                'travel_duration_minutes' => 0,
                'first_leg_duration_minutes' => 0,
            ];
        }

        $coordinates = collect([
            [
                (float) $branch->longitude,
                (float) $branch->latitude,
            ],
        ])
        ->concat(
            collect($stops)->map(
                fn (array $stop) => [
                    (float) $stop['store']['longitude'],
                    (float) $stop['store']['latitude'],
                ]
            )
        )
        ->push([
            (float) $branch->longitude,
            (float) $branch->latitude,
        ])
        ->all();

        $coordinateString = collect($coordinates)
            ->map(
                fn (array $point): string =>
                    $point[0] . ',' . $point[1]
            )
            ->implode(';');

        logger()->info('CONTROL_TOWER_OSRM_DEBUG', [
            'route_id' => $route->id,
            'stop_count' => count($stops),
            'coordinate_count' => count($coordinates),
            'coordinate_string' => $coordinateString,
        ]);

        $baseUrl = rtrim(
            (string) config(
                'services.osrm.url',
                env('OSRM_URL', 'http://127.0.0.1:5000')
            ),
            '/'
        );

        try {

            $response = Http::timeout(20)
                ->get(
                    $baseUrl
                    . '/route/v1/driving/'
                    . $coordinateString,
                    [
                        'overview' => 'full',
                        'geometries' => 'geojson',
                        'steps' => 'false',
                    ]
                );

            logger()->info('CONTROL_TOWER_OSRM_RESPONSE', [
                'route_id' => $route->id,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
            ]);

            if (
                $response->failed()
                || $response->json('code') !== 'Ok'
            ) {
                return $this->fallbackRouteMetrics(
                    $coordinates
                );
            }

            /*
            |--------------------------------------------------------------------------
            | GEOMETRY
            |--------------------------------------------------------------------------
            */

            $geometryCoordinates =
                $response->json(
                    'routes.0.geometry.coordinates'
                );

            if (! is_array($geometryCoordinates)) {
                return $this->fallbackRouteMetrics(
                    $coordinates
                );
            }

            $geometry = collect($geometryCoordinates)
                ->filter(
                    fn ($point): bool =>
                        is_array($point)
                        && count($point) >= 2
                )
                ->map(
                    fn (array $point): array => [
                        'lat' => (float) $point[1],
                        'lng' => (float) $point[0],
                    ]
                )
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | DISTANCE
            |--------------------------------------------------------------------------
            |
            | OSRM memberikan meter.
            | Kita simpan di payload sebagai kilometer.
            |
            */

            $distanceMeters =
                (float) (
                    $response->json(
                        'routes.0.distance'
                    )
                    ?? 0
                );

            $distanceKm =
                round(
                    $distanceMeters / 1000,
                    2
                );

            $durationSeconds =
                (float) (
                    $response->json(
                        'routes.0.duration'
                    )
                    ?? 0
                );

            $travelDurationMinutes =
                (int) round(
                    $durationSeconds / 60
                );
            
            $firstLegDurationSeconds =
                (float) (
                    $response->json(
                        'routes.0.legs.0.duration'
                    )
                    ?? 0
                );

            $firstLegDurationMinutes =
                (int) round(
                    $firstLegDurationSeconds / 60
                );

            logger()->info('CONTROL_TOWER_OSRM_METRICS', [
                'route_id' => $route->id,
                'distance_meters' => $distanceMeters,
                'distance_km' => $distanceKm,
                'duration_seconds' => $durationSeconds,
                'travel_duration_minutes' => $travelDurationMinutes,
                'geometry_count' => count($geometry),
            ]);

            return [
                'geometry' => $geometry,
                'distance_km' => $distanceKm,
                'travel_duration_minutes' => $travelDurationMinutes,
                'first_leg_duration_minutes' => $firstLegDurationMinutes,
            ];

        } catch (\Throwable) {

            return $this->fallbackRouteMetrics(
                $coordinates
            );
        }
    }

    protected function fallbackRouteMetrics(array $coordinates): array
    {
        return [
            'geometry' => collect($coordinates)
                ->map(
                    fn (array $point): array => [
                        'lat' => (float) $point[1],
                        'lng' => (float) $point[0],
                    ]
                )
                ->values()
                ->all(),

            'distance_km' => 0,

            'travel_duration_minutes' => 0,

            'first_leg_duration_minutes' => 0,
        ];
    }

    protected function routeTimeline(
        DeliveryRoute $route,
        array $stops,
        array $routeMetrics
    ): array {

        if (empty($stops)) {
            return [
                'start_time' => null,
                'finish_time' => null,
            ];
        }

        $firstStop = collect($stops)
            ->sortBy('sequence')
            ->first();

        $firstArrival =
            $firstStop['predicted_arrival']
            ?? null;

        if (! $firstArrival) {
            return [
                'start_time' => null,
                'finish_time' => null,
            ];
        }

        $firstLegDurationMinutes =
            (int) (
                $routeMetrics[
                    'first_leg_duration_minutes'
                ]
                ?? 0
            );

        $routeDate =
            $route->route_date?->toDateString();

        if (! $routeDate) {
            return [
                'start_time' => null,
                'finish_time' => null,
            ];
        }

        $routeStart =
            Carbon::parse(
                $routeDate
                . ' '
                . $firstArrival
            )
            ->subMinutes(
                max(
                    0,
                    $firstLegDurationMinutes
                )
            );

        $routeDurationMinutes =
            max(
                0,
                (int) (
                    $route
                        ->predicted_duration_minutes
                    ?? 0
                )
            );

        $routeFinish =
            $routeStart
                ->copy()
                ->addMinutes(
                    $routeDurationMinutes
                );

        return [
            'start_time' =>
                $routeStart->format('H:i'),

            'finish_time' =>
                $routeFinish->format('H:i'),
        ];
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
