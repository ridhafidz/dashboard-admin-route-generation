<?php

namespace App\Services;

use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RouteReoptimizationService
{
    protected const DEFAULT_BRANCH_START_TIME = '08:00';

    protected const DEFAULT_ROUTE_PREPARATION_MINUTES = 60;


    /*
    |--------------------------------------------------------------------------
    | PREVIEW
    |--------------------------------------------------------------------------
    |
    | Menghitung ulang route menggunakan Python,
    | TAPI belum mengubah database.
    |
    | Ini nantinya sangat berguna untuk:
    |
    | - rekomendasi Smart Reroute
    | - compare BEFORE / AFTER
    | - mencari target route terbaik
    |
    */

    public function preview(
        DeliveryRoute|int $route,
        ?Collection $stops = null
    ): array {

        $route =
            $this->loadRoute(
                $route
            );


        $this->assertRouteEditable(
            $route
        );


        /*
         * Kalau stops tidak diberikan,
         * gunakan stop existing route.
         */
        if ($stops === null) {

            $stops =
                $route
                    ->deliveryStops
                    ->sortBy(
                        'sequence_order'
                    )
                    ->values();
        }


        /*
         * Pastikan relation lengkap.
         */
        foreach ($stops as $stop) {

            if (
                ! $stop
                instanceof DeliveryStop
            ) {
                throw new RuntimeException(
                    'Collection route harus berisi DeliveryStop.'
                );
            }


            $stop->loadMissing([
                'store',
                'packages.order',
                'packages.product',
            ]);
        }


        $startTime =
            $this->resolveRouteStartTime(
                $route
            );


        $demands =
            $stops
                ->map(
                    fn (
                        DeliveryStop $stop
                    ): array =>
                        $this
                            ->buildDemandPayload(
                                $stop
                            )
                )
                ->values()
                ->all();


        $payload = [
            'branch' => [
                'latitude' =>
                    (float)
                    $route
                        ->branch
                        ->latitude,

                'longitude' =>
                    (float)
                    $route
                        ->branch
                        ->longitude,

                'start_time' =>
                    $startTime,
            ],

            'vehicle' =>
                $this
                    ->buildVehiclePayload(
                        $route
                    ),

            'demands' =>
                $demands,
        ];


        $result =
            $this->callOptimizer(
                $payload
            );


        $this->validateOptimizerResult(
            result:
                $result,

            stops:
                $stops
        );


        /*
         * Snapshot dipakai untuk concurrency validation
         * saat hasil akan disimpan.
         */
        $stopUuids =
            $stops
                ->pluck('uuid')
                ->map(
                    fn ($uuid): string =>
                        (string) $uuid
                )
                ->sort()
                ->values()
                ->all();


        $packageIds =
            $stops
                ->flatMap(
                    fn (
                        DeliveryStop $stop
                    ) =>
                        $stop
                            ->packages
                            ->pluck('id')
                )
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->sort()
                ->values()
                ->all();


        return array_merge(
            $result,
            [
                'start_time' =>
                    $startTime,

                /*
                 * Internal metadata.
                 */
                '_snapshot_stop_uuids' =>
                    $stopUuids,

                '_snapshot_package_ids' =>
                    $packageIds,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RE-OPTIMIZE + SAVE
    |--------------------------------------------------------------------------
    */

    public function reoptimize(
        DeliveryRoute|int $route
    ): array {

        $route =
            $this->loadRoute(
                $route
            );


        $preview =
            $this->preview(
                $route
            );


        $this->persistOptimization(
            route:
                $route,

            result:
                $preview
        );


        /*
         * Jangan expose metadata internal
         * ke caller.
         */
        unset(
            $preview[
                '_snapshot_stop_uuids'
            ],

            $preview[
                '_snapshot_package_ids'
            ]
        );


        return $preview;
    }

    /*
|--------------------------------------------------------------------------
| APPLY EXISTING PREVIEW
|--------------------------------------------------------------------------
|
| Menyimpan hasil preview yang SUDAH dihitung sebelumnya.
|
| Dipakai Smart Reroute supaya:
|
| 1. Python dipanggil sebelum transaction
| 2. DB baru di-lock
| 3. Stop dipindah
| 4. Hasil preview diterapkan secara atomic
|
*/

    public function applyPreview(
        DeliveryRoute|int $route,
        array $preview
    ): void {

        $route =
            $this->loadRoute(
                $route
            );

        $this->assertRouteEditable(
            $route
        );

        foreach (
            [
                'optimized_route',
                'start_time',
                '_snapshot_stop_uuids',
                '_snapshot_package_ids',
            ]
            as
            $requiredKey
        ) {

            if (
                ! array_key_exists(
                    $requiredKey,
                    $preview
                )
            ) {
                throw new RuntimeException(
                    "Preview re-optimization tidak memiliki {$requiredKey}."
                );
            }
        }


    /*
     * Setelah stop benar-benar dipindah,
     * membership route harus sama persis
     * dengan membership saat preview.
     */
        $this->validateOptimizerResult(
            result:
                $preview,

            stops:
                $route
                    ->deliveryStops
        );

        $this->persistOptimization(
            route:
                $route,

            result:
                $preview
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD ROUTE
    |--------------------------------------------------------------------------
    */

    protected function loadRoute(
        DeliveryRoute|int $route
    ): DeliveryRoute {

        $routeId =
            $route
            instanceof DeliveryRoute

                ? $route->id

                : $route;


        /** @var DeliveryRoute $loaded */
        $loaded =
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

                    'deliveryStops.packages.order',

                    'deliveryStops.packages.product',
                ])
                ->findOrFail(
                    $routeId
                );


        if (
            ! $loaded->branch
        ) {
            throw new RuntimeException(
                'Branch route tidak ditemukan.'
            );
        }


        if (
            $loaded
                ->branch
                ->latitude
            === null

            ||

            $loaded
                ->branch
                ->longitude
            === null
        ) {
            throw new RuntimeException(
                'Koordinat branch belum lengkap.'
            );
        }


        if (
            ! $loaded->vehicle
            ||
            ! $loaded
                ->vehicle
                ->vehicleType
        ) {
            throw new RuntimeException(
                'Vehicle atau Vehicle Type route tidak ditemukan.'
            );
        }


        return $loaded;
    }


    /*
    |--------------------------------------------------------------------------
    | ROUTE EDITABLE GUARD
    |--------------------------------------------------------------------------
    */

    protected function assertRouteEditable(
        DeliveryRoute $route
    ): void {

        $status =
            $this->enumValue(
                $route->status
            );


        if (
            $status
            !==
            'planned'
        ) {
            throw new RuntimeException(
                'Hanya route Planned yang dapat di-reoptimize.'
            );
        }


        if (
            $route
                ->approval_status
            !==
            'pending'
        ) {
            throw new RuntimeException(
                'Route sudah Approved dan tidak dapat di-reoptimize.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VEHICLE PAYLOAD
    |--------------------------------------------------------------------------
    */

    protected function buildVehiclePayload(
        DeliveryRoute $route
    ): array {

        $vehicle =
            $route->vehicle;

        $vehicleType =
            $vehicle->vehicleType;


        $boxType =
            $this->enumValue(
                $vehicleType
                    ->box_type
            );


        if (! $boxType) {
            throw new RuntimeException(
                'Vehicle belum mempunyai box type.'
            );
        }


        $capacity =
            (float)
            $vehicleType
                ->volume_m3;


        if (
            $capacity <= 0
        ) {
            throw new RuntimeException(
                'Kapasitas volume vehicle tidak valid.'
            );
        }


        if (
            ! $vehicle->uuid
        ) {
            throw new RuntimeException(
                'UUID vehicle tidak ditemukan.'
            );
        }


        return [
            'id' =>
                (string)
                $vehicle->uuid,

            'plate_number' =>
                (string)
                $vehicle
                    ->plate_number,

            'category' =>
                $this->enumValue(
                    $vehicleType
                        ->category
                ),

            'box_type' =>
                $boxType,

            'capacity_volume_m3' =>
                $capacity,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | STOP -> DELIVERY DEMAND
    |--------------------------------------------------------------------------
    |
    | Satu DeliveryStop existing dianggap sebagai
    | satu STORE + BOX TYPE demand.
    |
    */

    protected function buildDemandPayload(
        DeliveryStop $stop
    ): array {

        $store =
            $stop->store;


        if (! $store) {
            throw new RuntimeException(
                "Store pada stop #{$stop->id} tidak ditemukan."
            );
        }


        if (! $store->uuid) {
            throw new RuntimeException(
                "Store {$store->name} tidak mempunyai UUID."
            );
        }


        $packages =
            $stop
                ->packages
                ->values();


        if (
            $packages->isEmpty()
        ) {
            throw new RuntimeException(
                "Stop {$store->name} tidak mempunyai package."
            );
        }


        /*
         * Satu stop tidak boleh mencampur
         * Dry + Cold.
         */
        $boxTypes =
            $packages
                ->map(
                    fn ($package) =>
                        $this->enumValue(
                            $package
                                ->box_type
                        )
                )
                ->filter()
                ->unique()
                ->values();


        if (
            $boxTypes->count()
            !==
            1
        ) {
            throw new RuntimeException(
                "Stop {$store->name} memiliki box type yang tidak konsisten."
            );
        }


        $boxType =
            (string)
            $boxTypes->first();


        $firstPackage =
            $packages->first();


        $firstOrder =
            $firstPackage
                ?->order;


        /*
         * Sama seperti RouteGenerationService:
         *
         * Order snapshot
         * ↓
         * Store master
         */
        $latitude =
            $firstOrder
                ?->delivery_latitude
            ??
            $store->latitude;


        $longitude =
            $firstOrder
                ?->delivery_longitude
            ??
            $store->longitude;


        $address =
            $firstOrder
                ?->delivery_address
            ?:
            $store->address;


        if (
            $latitude === null
            ||
            $longitude === null
        ) {
            throw new RuntimeException(
                "Koordinat {$store->name} tidak valid."
            );
        }


        $totalVolume =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->volume_m3
                        ?? 0
                    )
            );


        if (
            $totalVolume <= 0
        ) {
            throw new RuntimeException(
                "Volume package {$store->name} tidak valid."
            );
        }


        $totalWeight =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->weight_kg
                        ?? 0
                    )
            );


        return [
            /*
             * UUID stop menjadi temporary demand ID.
             *
             * Ini penting supaya hasil Python
             * mudah dimapping kembali ke DeliveryStop.
             */
            'id' =>
                (string)
                $stop->uuid,

            'store_id' =>
                (string)
                $store->uuid,

            'store_name' =>
                (string)
                $store->name,

            'address' =>
                $address,

            'latitude' =>
                (float)
                $latitude,

            'longitude' =>
                (float)
                $longitude,

            'box_type' =>
                $boxType,

            'total_weight_kg' =>
                round(
                    $totalWeight,
                    4
                ),

            'total_volume_m3' =>
                round(
                    $totalVolume,
                    6
                ),

            'opening_time' =>
                $this->normalizeTime(
                    $store
                        ->opening_time
                ),

            'closing_time' =>
                $this->normalizeTime(
                    $store
                        ->closing_time
                ),

            'service_duration_minutes' =>
                (int) (
                    $store
                        ->service_duration_minutes
                    ?? 15
                ),

            'package_ids' =>
                $packages
                    ->pluck('uuid')
                    ->filter()
                    ->map(
                        fn ($uuid): string =>
                            (string) $uuid
                    )
                    ->values()
                    ->all(),

            'order_ids' =>
                $packages
                    ->map(
                        fn ($package) =>
                            $package
                                ->order
                                ?->uuid
                    )
                    ->filter()
                    ->unique()
                    ->map(
                        fn ($uuid): string =>
                            (string) $uuid
                    )
                    ->values()
                    ->all(),

            'items' =>
                $packages
                    ->map(
                        function (
                            $package
                        ): array {

                            return [
                                'package_id' =>
                                    (string)
                                    $package
                                        ->uuid,

                                'order_id' =>
                                    $package
                                        ->order
                                        ?->uuid,

                                'order_number' =>
                                    $package
                                        ->order
                                        ?->order_number,

                                'product_id' =>
                                    $package
                                        ->product
                                        ?->uuid,

                                'product_code' =>
                                    $package
                                        ->product
                                        ?->code,

                                'product_name' =>
                                    (string)
                                    $package
                                        ->item,

                                'quantity' =>
                                    (int)
                                    $package
                                        ->quantity,

                                'uom' =>
                                    $this->enumValue(
                                        $package
                                            ->uom
                                    ),

                                'weight_kg' =>
                                    (float) (
                                        $package
                                            ->weight_kg
                                        ?? 0
                                    ),

                                'volume_m3' =>
                                    (float) (
                                        $package
                                            ->volume_m3
                                        ?? 0
                                    ),

                                'unit_price' =>
                                    $package
                                        ->unit_price
                                    !== null

                                        ? (float)
                                            $package
                                                ->unit_price

                                        : null,

                                'total_price' =>
                                    $package
                                        ->total_price
                                    !== null

                                        ? (float)
                                            $package
                                                ->total_price

                                        : null,
                            ];
                        }
                    )
                    ->values()
                    ->all(),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | CALL PYTHON
    |--------------------------------------------------------------------------
    */

    protected function callOptimizer(
        array $payload
    ): array {

        $mlUrl =
            config(
                'services.ml.url'
            );


        if (
            ! is_string(
                $mlUrl
            )
            ||
            trim(
                $mlUrl
            ) === ''
        ) {
            throw new RuntimeException(
                'Konfigurasi services.ml.url belum diisi.'
            );
        }


        $response =
            Http::timeout(120)
                ->post(
                    rtrim(
                        $mlUrl,
                        '/'
                    )
                    . '/optimize-existing-route',

                    $payload
                );


        if (
            $response->failed()
        ) {
            throw new RuntimeException(
                'Re-optimization service gagal: '
                . (
                    $response->json(
                        'detail'
                    )
                    ??
                    $response->body()
                )
            );
        }


        $result =
            $response->json();


        if (
            ! is_array(
                $result
            )
        ) {
            throw new RuntimeException(
                'Response re-optimization tidak valid.'
            );
        }


        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE PYTHON RESULT
    |--------------------------------------------------------------------------
    */

    protected function validateOptimizerResult(
        array $result,
        Collection $stops
    ): void {

        $optimizedRoute =
            $result[
                'optimized_route'
            ]
            ?? null;


        if (
            ! is_array(
                $optimizedRoute
            )
        ) {
            throw new RuntimeException(
                'Optimizer tidak mengembalikan optimized_route.'
            );
        }


        if (
            count(
                $optimizedRoute
            )
            !==
            $stops->count()
        ) {
            throw new RuntimeException(
                'Jumlah stop hasil optimizer tidak sama dengan snapshot route.'
            );
        }


        $expectedDemandIds =
            $stops
                ->pluck('uuid')
                ->map(
                    fn ($uuid): string =>
                        (string) $uuid
                )
                ->sort()
                ->values()
                ->all();


        $actualDemandIds =
            collect(
                $optimizedRoute
            )
                ->pluck(
                    'demand_id'
                )
                ->map(
                    fn ($id): string =>
                        (string) $id
                )
                ->sort()
                ->values()
                ->all();


        if (
            $expectedDemandIds
            !==
            $actualDemandIds
        ) {
            throw new RuntimeException(
                'Demand hasil optimizer tidak sesuai dengan DeliveryStop route.'
            );
        }


        $sequences =
            collect(
                $optimizedRoute
            )
                ->pluck(
                    'sequence'
                )
                ->map(
                    fn ($sequence): int =>
                        (int) $sequence
                )
                ->sort()
                ->values()
                ->all();


        $expectedSequences =
            $stops->isEmpty()
                ? []
                : range(
                    1,
                    $stops->count()
                );


        if (
            $sequences
            !==
            $expectedSequences
        ) {
            throw new RuntimeException(
                'Sequence hasil optimizer tidak valid.'
            );
        }


        foreach (
            $optimizedRoute
            as
            $row
        ) {

            foreach (
                [
                    'arrival_time',
                    'service_start',
                    'service_end',
                ]
                as
                $timeKey
            ) {

                if (
                    ! isset(
                        $row[
                            $timeKey
                        ]
                    )
                ) {
                    throw new RuntimeException(
                        "Optimizer tidak mengembalikan {$timeKey}."
                    );
                }


                $this->normalizeTime(
                    (string)
                    $row[
                        $timeKey
                    ]
                );
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE OPTIMIZATION
    |--------------------------------------------------------------------------
    */

    protected function persistOptimization(
        DeliveryRoute $route,
        array $result
    ): void {

        $snapshotStopUuids =
            $result[
                '_snapshot_stop_uuids'
            ]
            ?? [];


        $snapshotPackageIds =
            $result[
                '_snapshot_package_ids'
            ]
            ?? [];


        $optimizedRoute =
            $result[
                'optimized_route'
            ]
            ?? [];


        DB::transaction(
            function () use (
                $route,
                $snapshotStopUuids,
                $snapshotPackageIds,
                $optimizedRoute,
                $result
            ): void {

                /** @var DeliveryRoute $lockedRoute */
                $lockedRoute =
                    DeliveryRoute::query()
                        ->whereKey(
                            $route->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                $this->assertRouteEditable(
                    $lockedRoute
                );


                /*
                 * Lock stop snapshot.
                 */
                $currentStops =
                    DeliveryStop::query()
                        ->where(
                            'delivery_route_id',
                            $lockedRoute
                                ->id
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();


                $currentStops->load(
                    'packages'
                );


                $currentStopUuids =
                    $currentStops
                        ->pluck('uuid')
                        ->map(
                            fn ($uuid): string =>
                                (string) $uuid
                        )
                        ->sort()
                        ->values()
                        ->all();


                if (
                    $currentStopUuids
                    !==
                    $snapshotStopUuids
                ) {
                    throw new RuntimeException(
                        'DeliveryStop berubah saat proses re-optimization. Silakan ulangi.'
                    );
                }


                $currentPackageIds =
                    $currentStops
                        ->flatMap(
                            fn (
                                DeliveryStop $stop
                            ) =>
                                $stop
                                    ->packages
                                    ->pluck('id')
                        )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->sort()
                        ->values()
                        ->all();


                if (
                    $currentPackageIds
                    !==
                    $snapshotPackageIds
                ) {
                    throw new RuntimeException(
                        'Package route berubah saat proses re-optimization. Silakan ulangi.'
                    );
                }


                $optimizedByDemand =
                    collect(
                        $optimizedRoute
                    )
                        ->keyBy(
                            fn (
                                array $row
                            ): string =>
                                (string)
                                $row[
                                    'demand_id'
                                ]
                        );


                foreach (
                    $currentStops
                    as
                    $stop
                ) {

                    $row =
                        $optimizedByDemand
                            ->get(
                                (string)
                                $stop->uuid
                            );


                    if (! $row) {
                        throw new RuntimeException(
                            "Hasil optimizer untuk Stop #{$stop->id} tidak ditemukan."
                        );
                    }


                    $stop->forceFill([
                        'sequence_order' =>
                            (int)
                            $row[
                                'sequence'
                            ],

                        'predicted_arrival_time' =>
                            $this
                                ->routeDateTime(
                                    $lockedRoute,
                                    (string)
                                    $row[
                                        'arrival_time'
                                    ]
                                ),

                        'predicted_service_start_time' =>
                            $this
                                ->routeDateTime(
                                    $lockedRoute,
                                    (string)
                                    $row[
                                        'service_start'
                                    ]
                                ),

                        'predicted_service_end_time' =>
                            $this
                                ->routeDateTime(
                                    $lockedRoute,
                                    (string)
                                    $row[
                                        'service_end'
                                    ]
                                ),

                        'predicted_waiting_minutes' =>
                            (int) (
                                $row[
                                    'waiting_minutes'
                                ]
                                ?? 0
                            ),
                    ]);


                    $stop->save();
                }


                /*
                 * predicted_duration_minutes existing
                 * tetap memakai:
                 *
                 * start route
                 * →
                 * service selesai stop terakhir
                 *
                 * bukan sampai kendaraan kembali branch.
                 */
                $predictedDuration =
                    0;


                if (
                    ! empty(
                        $optimizedRoute
                    )
                ) {

                    $lastStop =
                        collect(
                            $optimizedRoute
                        )
                            ->sortBy(
                                'sequence'
                            )
                            ->last();


                    $predictedDuration =
                        $this
                            ->minutesBetween(
                                (string)
                                $result[
                                    'start_time'
                                ],

                                (string)
                                $lastStop[
                                    'service_end'
                                ]
                            );
                }


                $lockedRoute->forceFill([
                    'predicted_duration_minutes' =>
                        max(
                            0,
                            $predictedDuration
                        ),

                    'predicted_package_count' =>
                        count(
                            $snapshotPackageIds
                        ),
                ]);


                $lockedRoute->save();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ROUTE START TIME
    |--------------------------------------------------------------------------
    |
    | Jangan menambah buffer +60 setiap kali admin klik reroute.
    |
    | Start awal direkonstruksi dari waktu route dibuat.
    |
    | Kalau waktu tersebut sudah lewat tetapi route masih Pending,
    | gunakan waktu sekarang sebagai baseline.
    |
    */

    protected function resolveRouteStartTime(
        DeliveryRoute $route
    ): string {

        $branchStartTime =
            $this->normalizeTime(
                $route
                    ->branch
                    ->start_time
            )
            ??
            self::DEFAULT_BRANCH_START_TIME;


        $routeDate =
            Carbon::parse(
                $route->route_date
            )
                ->startOfDay();


        $branchStartDateTime =
            $routeDate
                ->copy()
                ->setTimeFromTimeString(
                    $branchStartTime
                );


        $createdAt =
            $route->created_at

                ? Carbon::parse(
                    $route->created_at
                )

                : $branchStartDateTime
                    ->copy();


        /*
         * Route normalnya dibuat pada route_date.
         *
         * Kalau dibuat sebelumnya,
         * branch start tetap menjadi baseline.
         */
        $planningBase =
            $createdAt
                ->isSameDay(
                    $routeDate
                )
            &&
            $createdAt
                ->greaterThan(
                    $branchStartDateTime
                )

                ? $createdAt->copy()

                : $branchStartDateTime
                    ->copy();


        $plannedStart =
            $planningBase
                ->copy()
                ->addMinutes(
                    self::DEFAULT_ROUTE_PREPARATION_MINUTES
                );


        /*
         * Jangan menghasilkan ETA di masa lalu
         * ketika route sedang direview agak lama.
         *
         * Tetapi jangan tambah buffer 60 menit lagi.
         */
        $now =
            now();


        if (
            $now->isSameDay(
                $routeDate
            )
            &&
            $now->greaterThan(
                $plannedStart
            )
        ) {
            $plannedStart =
                $now->copy();
        }


        return
            $plannedStart
                ->format(
                    'H:i'
                );
    }


    /*
    |--------------------------------------------------------------------------
    | ROUTE DATETIME
    |--------------------------------------------------------------------------
    */

    protected function routeDateTime(
        DeliveryRoute $route,
        string $time
    ): Carbon {

        $normalized =
            $this->normalizeTime(
                $time
            );


        if (! $normalized) {
            throw new RuntimeException(
                "Waktu optimizer tidak valid: {$time}"
            );
        }


        return Carbon::parse(
            $route->route_date
        )
            ->startOfDay()
            ->setTimeFromTimeString(
                $normalized
            );
    }


    /*
    |--------------------------------------------------------------------------
    | TIME HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalizeTime(
        mixed $value
    ): ?string {

        if (
            $value === null
        ) {
            return null;
        }


        if (
            $value
            instanceof
            \DateTimeInterface
        ) {
            return
                $value
                    ->format(
                        'H:i'
                    );
        }


        $value =
            trim(
                (string)
                $value
            );


        if (
            $value === ''
        ) {
            return null;
        }


        if (
            ! preg_match(
                '/^(\d{1,2}):(\d{2})(?::\d{2})?$/',
                $value,
                $matches
            )
        ) {
            throw new RuntimeException(
                "Format waktu tidak valid: {$value}"
            );
        }


        $hour =
            (int)
            $matches[1];


        $minute =
            (int)
            $matches[2];


        if (
            $hour > 23
            ||
            $minute > 59
        ) {
            throw new RuntimeException(
                "Format waktu tidak valid: {$value}"
            );
        }


        return sprintf(
            '%02d:%02d',
            $hour,
            $minute
        );
    }


    protected function minutesBetween(
        string $start,
        string $end
    ): int {

        [
            $startHour,
            $startMinute,
        ] =
            array_map(
                'intval',
                explode(
                    ':',
                    $start
                )
            );


        [
            $endHour,
            $endMinute,
        ] =
            array_map(
                'intval',
                explode(
                    ':',
                    $end
                )
            );


        $startMinutes =
            $startHour * 60
            +
            $startMinute;


        $endMinutes =
            $endHour * 60
            +
            $endMinute;


        if (
            $endMinutes
            <
            $startMinutes
        ) {
            $endMinutes +=
                24 * 60;
        }


        return
            $endMinutes
            -
            $startMinutes;
    }


    /*
    |--------------------------------------------------------------------------
    | ENUM HELPER
    |--------------------------------------------------------------------------
    */

    protected function enumValue(
        mixed $value
    ): ?string {

        if (
            $value
            instanceof
            BackedEnum
        ) {
            return
                (string)
                $value->value;
        }


        if (
            $value === null
        ) {
            return null;
        }


        $value =
            trim(
                (string)
                $value
            );


        return
            $value !== ''
                ? $value
                : null;
    }
}