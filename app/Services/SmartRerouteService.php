<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmartRerouteService
{
    public function __construct(
        protected RouteReoptimizationService $reoptimizer
    ) {
    }


    /*
    |--------------------------------------------------------------------------
    | RECOMMENDATIONS
    |--------------------------------------------------------------------------
    |
    | READ ONLY.
    |
    | Tidak ada perubahan database.
    |
    | Flow:
    |
    | SOURCE:
    | Route A = A -> B -> X -> C
    |
    | TARGET:
    | Route B = D -> E -> F
    |
    | Simulasi:
    |
    | Route A = A -> B -> C
    | Route B = D -> X -> E -> F
    |
    | Kemudian bandingkan:
    |
    | - total distance before / after
    | - total duration before / after
    | - tambahan beban target
    | - capacity utilization
    |
    */

    public function recommendations(
        DeliveryRoute|int $sourceRoute,
        DeliveryStop|int $stop
    ): array {

        $sourceRoute =
            $this->loadRoute(
                $sourceRoute
            );


        $this->assertEditable(
            $sourceRoute
        );


        $stop =
            $this->loadSourceStop(
                $sourceRoute,
                $stop
            );


        $stopBoxType =
            $this->resolveStopBoxType(
                $stop
            );


        $stopVolumeM3 =
            $this->calculateStopVolume(
                $stop
            );


        /*
        |--------------------------------------------------------------------------
        | SOURCE BEFORE
        |--------------------------------------------------------------------------
        */

        $sourceBefore =
            $this->reoptimizer
                ->preview(
                    $sourceRoute
                );


        /*
        |--------------------------------------------------------------------------
        | SOURCE AFTER
        |--------------------------------------------------------------------------
        |
        | Simulasikan stop dikeluarkan dari source.
        |
        */

        $sourceAfterStops =
            $sourceRoute
                ->deliveryStops
                ->reject(
                    fn (
                        DeliveryStop $item
                    ): bool =>
                        (int) $item->id
                        ===
                        (int) $stop->id
                )
                ->values();


        $sourceAfter =
            $this->reoptimizer
                ->preview(
                    $sourceRoute,
                    $sourceAfterStops
                );


        /*
        |--------------------------------------------------------------------------
        | TARGET ROUTES
        |--------------------------------------------------------------------------
        */

        $targetRoutes =
            DeliveryRoute::query()
                ->with([
                    'branch',

                    'driver',

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

                /*
                 * Cabang sama.
                 */
                ->where(
                    'branch_id',
                    $sourceRoute
                        ->branch_id
                )

                /*
                 * Tanggal route sama.
                 */
                ->whereDate(
                    'route_date',
                    $sourceRoute
                        ->route_date
                )

                /*
                 * Hanya draft route.
                 */
                ->where(
                    'status',
                    RouteStatus
                        ::Planned
                        ->value
                )

                ->where(
                    'approval_status',
                    'pending'
                )

                /*
                 * Jangan source route sendiri.
                 */
                ->where(
                    'id',
                    '!=',
                    $sourceRoute
                        ->id
                )

                ->orderBy('id')

                ->get();


        $candidates = [];

        $rejected = [];


        foreach (
            $targetRoutes
            as
            $targetRoute
        ) {

            try {

                /*
                |--------------------------------------------------------------------------
                | TARGET BASIC VALIDATION
                |--------------------------------------------------------------------------
                */

                $targetBoxType =
                    $this->enumValue(
                        $targetRoute
                            ->vehicle
                            ?->vehicleType
                            ?->box_type
                    );


                if (
                    ! $targetBoxType
                ) {
                    throw new RuntimeException(
                        'Vehicle target belum mempunyai box type.'
                    );
                }


                if (
                    $targetBoxType
                    !==
                    $stopBoxType
                ) {
                    throw new RuntimeException(
                        "Box type target {$targetBoxType} "
                        . "tidak compatible dengan "
                        . "stop {$stopBoxType}."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CAPACITY
                |--------------------------------------------------------------------------
                */

                $capacityM3 =
                    (float) (
                        $targetRoute
                            ->vehicle
                            ?->vehicleType
                            ?->volume_m3
                        ?? 0
                    );


                if (
                    $capacityM3 <= 0
                ) {
                    throw new RuntimeException(
                        'Kapasitas volume vehicle target tidak valid.'
                    );
                }


                $currentTargetVolumeM3 =
                    $this
                        ->calculateRouteVolume(
                            $targetRoute
                                ->deliveryStops
                        );


                $volumeAfterM3 =
                    $currentTargetVolumeM3
                    +
                    $stopVolumeM3;


                if (
                    $volumeAfterM3
                    >
                    $capacityM3
                    + 0.000001
                ) {
                    throw new RuntimeException(
                        'Kapasitas target tidak cukup. '
                        . "Volume setelah reroute "
                        . "{$volumeAfterM3} m3 dari "
                        . "{$capacityM3} m3."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | TARGET BEFORE
                |--------------------------------------------------------------------------
                */

                $targetBefore =
                    $this->reoptimizer
                        ->preview(
                            $targetRoute
                        );


                /*
                |--------------------------------------------------------------------------
                | TARGET AFTER
                |--------------------------------------------------------------------------
                |
                | Stop SOURCE hanya dimasukkan ke Collection
                | untuk SIMULASI.
                |
                | Belum ada UPDATE delivery_route_id.
                |
                */

                $targetAfterStops =
                    $targetRoute
                        ->deliveryStops
                        ->concat([
                            $stop,
                        ])
                        ->values();


                $targetAfter =
                    $this->reoptimizer
                        ->preview(
                            $targetRoute,
                            $targetAfterStops
                        );


                /*
                |--------------------------------------------------------------------------
                | BEFORE TOTAL
                |--------------------------------------------------------------------------
                */

                $distanceBeforeKm =
                    $this->floatMetric(
                        $sourceBefore,
                        'total_distance_km'
                    )
                    +
                    $this->floatMetric(
                        $targetBefore,
                        'total_distance_km'
                    );


                $durationBeforeMinutes =
                    $this->intMetric(
                        $sourceBefore,
                        'route_duration_minutes'
                    )
                    +
                    $this->intMetric(
                        $targetBefore,
                        'route_duration_minutes'
                    );


                /*
                |--------------------------------------------------------------------------
                | AFTER TOTAL
                |--------------------------------------------------------------------------
                */

                $distanceAfterKm =
                    $this->floatMetric(
                        $sourceAfter,
                        'total_distance_km'
                    )
                    +
                    $this->floatMetric(
                        $targetAfter,
                        'total_distance_km'
                    );


                $durationAfterMinutes =
                    $this->intMetric(
                        $sourceAfter,
                        'route_duration_minutes'
                    )
                    +
                    $this->intMetric(
                        $targetAfter,
                        'route_duration_minutes'
                    );


                /*
                |--------------------------------------------------------------------------
                | TARGET IMPACT
                |--------------------------------------------------------------------------
                */

                $targetExtraDistanceKm =
                    $this->floatMetric(
                        $targetAfter,
                        'total_distance_km'
                    )
                    -
                    $this->floatMetric(
                        $targetBefore,
                        'total_distance_km'
                    );


                $targetExtraDurationMinutes =
                    $this->intMetric(
                        $targetAfter,
                        'route_duration_minutes'
                    )
                    -
                    $this->intMetric(
                        $targetBefore,
                        'route_duration_minutes'
                    );


                /*
                |--------------------------------------------------------------------------
                | SOURCE SAVING
                |--------------------------------------------------------------------------
                */

                $sourceDistanceSavingKm =
                    $this->floatMetric(
                        $sourceBefore,
                        'total_distance_km'
                    )
                    -
                    $this->floatMetric(
                        $sourceAfter,
                        'total_distance_km'
                    );


                $sourceDurationSavingMinutes =
                    $this->intMetric(
                        $sourceBefore,
                        'route_duration_minutes'
                    )
                    -
                    $this->intMetric(
                        $sourceAfter,
                        'route_duration_minutes'
                    );


                /*
                |--------------------------------------------------------------------------
                | NET EFFECT
                |--------------------------------------------------------------------------
                |
                | NEGATIVE:
                | reroute membuat total lebih kecil.
                |
                | POSITIVE:
                | reroute menambah total.
                |
                */

                $netDistanceDeltaKm =
                    $distanceAfterKm
                    -
                    $distanceBeforeKm;


                $netDurationDeltaMinutes =
                    $durationAfterMinutes
                    -
                    $durationBeforeMinutes;


                /*
                |--------------------------------------------------------------------------
                | CAPACITY AFTER
                |--------------------------------------------------------------------------
                */

                $capacityUtilizationAfter =
                    $capacityM3 > 0

                        ? (
                            $volumeAfterM3
                            /
                            $capacityM3
                        )

                        : 0.0;


                /*
                |--------------------------------------------------------------------------
                | INSERTED POSITION
                |--------------------------------------------------------------------------
                */

                $insertedRow =
                    collect(
                        $targetAfter[
                            'optimized_route'
                        ]
                        ?? []
                    )
                        ->first(
                            fn (
                                array $row
                            ): bool =>
                                (string) (
                                    $row[
                                        'demand_id'
                                    ]
                                    ?? ''
                                )
                                ===
                                (string)
                                $stop->uuid
                        );


                /*
                |--------------------------------------------------------------------------
                | CANDIDATE RESULT
                |--------------------------------------------------------------------------
                */

                $candidates[] = [

                    'target_route_id' =>
                        (int)
                        $targetRoute
                            ->id,

                    'target_route_uuid' =>
                        (string)
                        $targetRoute
                            ->uuid,

                    /*
                     * Driver.
                     */
                    'driver_id' =>
                        $targetRoute
                            ->driver
                            ?->id,

                    'driver_name' =>
                        $targetRoute
                            ->driver
                            ?->name,

                    /*
                     * Vehicle.
                     */
                    'vehicle_id' =>
                        $targetRoute
                            ->vehicle
                            ?->id,

                    'vehicle_uuid' =>
                        $targetRoute
                            ->vehicle
                            ?->uuid,

                    'vehicle_plate' =>
                        $targetRoute
                            ->vehicle
                            ?->plate_number,

                    'vehicle_category' =>
                        $this->enumValue(
                            $targetRoute
                                ->vehicle
                                ?->vehicleType
                                ?->category
                        ),

                    'box_type' =>
                        $targetBoxType,

                    /*
                     * Posisi hasil OR-Tools.
                     */
                    'suggested_sequence' =>
                        $insertedRow
                            ? (int) (
                                $insertedRow[
                                    'sequence'
                                ]
                                ?? 0
                            )
                            : null,

                    'suggested_arrival_time' =>
                        $insertedRow[
                            'arrival_time'
                        ]
                        ?? null,

                    /*
                     * Volume.
                     */
                    'current_volume_m3' =>
                        round(
                            $currentTargetVolumeM3,
                            6
                        ),

                    'stop_volume_m3' =>
                        round(
                            $stopVolumeM3,
                            6
                        ),

                    'volume_after_m3' =>
                        round(
                            $volumeAfterM3,
                            6
                        ),

                    'capacity_volume_m3' =>
                        round(
                            $capacityM3,
                            6
                        ),

                    'capacity_utilization_after' =>
                        round(
                            $capacityUtilizationAfter,
                            4
                        ),

                    'capacity_utilization_after_percent' =>
                        round(
                            $capacityUtilizationAfter
                            * 100,
                            2
                        ),

                    /*
                     * Dampak ke TARGET.
                     */
                    'target_extra_distance_km' =>
                        round(
                            $targetExtraDistanceKm,
                            2
                        ),

                    'target_extra_duration_minutes' =>
                        $targetExtraDurationMinutes,

                    /*
                     * Saving pada SOURCE.
                     */
                    'source_distance_saving_km' =>
                        round(
                            $sourceDistanceSavingKm,
                            2
                        ),

                    'source_duration_saving_minutes' =>
                        $sourceDurationSavingMinutes,

                    /*
                     * Combined BEFORE.
                     */
                    'combined_distance_before_km' =>
                        round(
                            $distanceBeforeKm,
                            2
                        ),

                    'combined_duration_before_minutes' =>
                        $durationBeforeMinutes,

                    /*
                     * Combined AFTER.
                     */
                    'combined_distance_after_km' =>
                        round(
                            $distanceAfterKm,
                            2
                        ),

                    'combined_duration_after_minutes' =>
                        $durationAfterMinutes,

                    /*
                     * NET.
                     *
                     * Minus = saving.
                     * Plus  = tambahan.
                     */
                    'net_distance_delta_km' =>
                        round(
                            $netDistanceDeltaKm,
                            2
                        ),

                    'net_duration_delta_minutes' =>
                        $netDurationDeltaMinutes,

                    /*
                     * Full preview untuk tahap berikutnya.
                     */
                    'source_after' =>
                        $sourceAfter,

                    'target_after' =>
                        $targetAfter,
                ];

            } catch (
                \Throwable $e
            ) {

                /*
                 * Satu candidate gagal tidak boleh
                 * menggagalkan seluruh recommendation.
                 */

                $rejected[] = [

                    'target_route_id' =>
                        (int)
                        $targetRoute
                            ->id,

                    'driver_name' =>
                        $targetRoute
                            ->driver
                            ?->name,

                    'vehicle_plate' =>
                        $targetRoute
                            ->vehicle
                            ?->plate_number,

                    'reason' =>
                        $e->getMessage(),
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RANKING
        |--------------------------------------------------------------------------
        |
        | Prioritas:
        |
        | 1. Net duration paling kecil
        | 2. Net distance paling kecil
        | 3. Extra duration target paling kecil
        | 4. Extra distance target paling kecil
        |
        */

        $candidates =
            collect(
                $candidates
            )
                ->sort(
                    function (
                        array $a,
                        array $b
                    ): int {

                        return [
                            $a[
                                'net_duration_delta_minutes'
                            ],

                            $a[
                                'net_distance_delta_km'
                            ],

                            $a[
                                'target_extra_duration_minutes'
                            ],

                            $a[
                                'target_extra_distance_km'
                            ],
                        ]
                        <=>
                        [
                            $b[
                                'net_duration_delta_minutes'
                            ],

                            $b[
                                'net_distance_delta_km'
                            ],

                            $b[
                                'target_extra_duration_minutes'
                            ],

                            $b[
                                'target_extra_distance_km'
                            ],
                        ];
                    }
                )
                ->values()
                ->map(
                    function (
                        array $row,
                        int $index
                    ): array {

                        $row[
                            'rank'
                        ] =
                            $index + 1;


                        return $row;
                    }
                )
                ->all();


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'source_route_id' =>
                (int)
                $sourceRoute
                    ->id,

            'stop_id' =>
                (int)
                $stop
                    ->id,

            'stop_uuid' =>
                (string)
                $stop
                    ->uuid,

            'store_id' =>
                $stop
                    ->store
                    ?->id,

            'store_name' =>
                $stop
                    ->store
                    ?->name,

            'box_type' =>
                $stopBoxType,

            'stop_volume_m3' =>
                round(
                    $stopVolumeM3,
                    6
                ),

            /*
             * Kondisi source asli.
             */
            'source_before' =>
                $sourceBefore,

            /*
             * Kondisi source jika stop dikeluarkan.
             */
            'source_after' =>
                $sourceAfter,

            'candidate_count' =>
                count(
                    $candidates
                ),

            'candidates' =>
                $candidates,

            /*
             * Untuk debugging / UI optional.
             */
            'rejected_count' =>
                count(
                    $rejected
                ),

            'rejected' =>
                $rejected,
        ];
    }

/*
|--------------------------------------------------------------------------
| EXECUTE REROUTE
|--------------------------------------------------------------------------
|
| Benar-benar memindahkan satu DeliveryStop:
|
| SOURCE ROUTE
|     ↓
| TARGET ROUTE
|
| Python dihitung SEBELUM transaction.
|
| Database update dilakukan secara atomic.
|
*/

    public function executeReroute(
        DeliveryRoute|int $sourceRoute,
        DeliveryStop|int $stop,
        DeliveryRoute|int $targetRoute
    ): array {

    /*
    |--------------------------------------------------------------------------
    | LOAD SOURCE
    |--------------------------------------------------------------------------
    */

        $sourceRoute =
            $this->loadRoute(
                $sourceRoute
            );

        $this->assertEditable(
            $sourceRoute
        );

        $stop =
            $this->loadSourceStop(
                $sourceRoute,
                $stop
            );

    /*
    |--------------------------------------------------------------------------
    | LOAD TARGET
    |--------------------------------------------------------------------------
    */

        $targetRoute =
            $this->loadRoute(
                $targetRoute
            );

        $this->assertEditable(
            $targetRoute
        );

        if (
            (int) $sourceRoute->id
            ===
            (int) $targetRoute->id
        ) {
            throw new RuntimeException(
                'Source Route dan Target Route tidak boleh sama.'
            );
        }

    /*
    |--------------------------------------------------------------------------
    | SAME BRANCH
    |--------------------------------------------------------------------------
    */

        if (
            (int) $sourceRoute->branch_id
            !==
            (int) $targetRoute->branch_id
        ) {
            throw new RuntimeException(
                'Reroute hanya dapat dilakukan pada cabang yang sama.'
            );
        }

    /*
    |--------------------------------------------------------------------------
    | SAME ROUTE DATE
    |--------------------------------------------------------------------------
    */

        $sourceDate =
            $this->routeDateString(
                $sourceRoute
                    ->route_date
            );

        $targetDate =
            $this->routeDateString(
                $targetRoute
                    ->route_date
            );

        if (
            $sourceDate
            !==
            $targetDate
        ) {
            throw new RuntimeException(
                'Reroute hanya dapat dilakukan pada tanggal pengiriman yang sama.'
            );
        }

    /*
    |--------------------------------------------------------------------------
    | BOX TYPE
    |--------------------------------------------------------------------------
    */

        $stopBoxType =
            $this->resolveStopBoxType(
                $stop
            );

        $targetBoxType =
            $this->enumValue(
                $targetRoute
                    ->vehicle
                    ?->vehicleType
                    ?->box_type
            );

        if (
            ! $targetBoxType
        ) {
            throw new RuntimeException(
                'Target Vehicle belum mempunyai Box Type.'
            );
        }

        if (
            $stopBoxType
            !==
            $targetBoxType
        ) {
            throw new RuntimeException(
                "Box Type tidak compatible. "
                . "Stop={$stopBoxType}, "
                . "Target={$targetBoxType}."
            );
        }

    /*
    |--------------------------------------------------------------------------
    | CAPACITY TARGET
    |--------------------------------------------------------------------------
    */

        $stopVolumeM3 =
            $this->calculateStopVolume(
                $stop
            );

        $targetCurrentVolumeM3 =
            $this->calculateRouteVolume(
                $targetRoute
                    ->deliveryStops
            );

        $targetCapacityM3 =
            (float) (
                $targetRoute
                    ->vehicle
                    ?->vehicleType
                    ?->volume_m3
                ?? 0
            );

        if (
            $targetCapacityM3 <= 0
        ) {
            throw new RuntimeException(
                'Kapasitas Target Vehicle tidak valid.'
            );
        }

        $targetVolumeAfterM3 =
            $targetCurrentVolumeM3
            +
            $stopVolumeM3;

        if (
            $targetVolumeAfterM3
            >
            $targetCapacityM3
            + 0.000001
        ) {
            throw new RuntimeException(
                'Target Vehicle tidak memiliki kapasitas yang cukup. '
                . "Volume setelah reroute {$targetVolumeAfterM3} m3 "
                . "dari kapasitas {$targetCapacityM3} m3."
            );
        }

    /*
    |--------------------------------------------------------------------------
    | SNAPSHOT BEFORE
    |--------------------------------------------------------------------------
    |
    | Nanti setelah lock kita pastikan membership belum berubah.
    |
    */

        $sourceStopUuidsBefore =
            $sourceRoute
                ->deliveryStops
                ->pluck('uuid')
                ->map(
                    fn ($uuid): string =>
                        (string) $uuid
                )
                ->sort()
                ->values()
                ->all();

        $targetStopUuidsBefore =
            $targetRoute
                ->deliveryStops
                ->pluck('uuid')
                ->map(
                    fn ($uuid): string =>
                        (string) $uuid
                )
                ->sort()
                ->values()
                ->all();

    /*
    |--------------------------------------------------------------------------
    | SIMULATE SOURCE AFTER
    |--------------------------------------------------------------------------
    */

        $sourceAfterStops =
            $sourceRoute
                ->deliveryStops
                ->reject(
                    fn (
                        DeliveryStop $item
                    ): bool =>
                        (int) $item->id
                        ===
                        (int) $stop->id
                )
                ->values();

        $sourceAfter =
            $this->reoptimizer
                ->preview(
                    $sourceRoute,
                    $sourceAfterStops
                );

    /*
    |--------------------------------------------------------------------------
    | SIMULATE TARGET AFTER
    |--------------------------------------------------------------------------
    */

        $targetAfterStops =
            $targetRoute
                ->deliveryStops
                ->concat([
                    $stop,
                ])
                ->values();

        $targetAfter =
            $this->reoptimizer
                ->preview(
                    $targetRoute,
                    $targetAfterStops
                );

    /*
    |--------------------------------------------------------------------------
    | DATABASE TRANSACTION
    |--------------------------------------------------------------------------
    |
    | Google + Python SUDAH selesai.
    |
    | Sekarang baru lock DB.
    |
    */

        DB::transaction(
            function () use (
                $sourceRoute,
                $targetRoute,
                $stop,
                $sourceStopUuidsBefore,
                $targetStopUuidsBefore,
                $sourceAfter,
                $targetAfter,
                $stopBoxType
            ): void {
            /*
            |--------------------------------------------------------------------------
            | LOCK ROUTES
            |--------------------------------------------------------------------------
            |
            | ID diurutkan untuk mengurangi risiko deadlock.
            |
            */

                $lockedRoutes =
                    DeliveryRoute::query()
                        ->whereIn(
                            'id',
                            [
                                $sourceRoute->id,
                                $targetRoute->id,
                            ]
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                /** @var DeliveryRoute|null $lockedSource */
                $lockedSource =
                    $lockedRoutes->get(
                        $sourceRoute->id
                    );

                /** @var DeliveryRoute|null $lockedTarget */
                $lockedTarget =
                    $lockedRoutes->get(
                        $targetRoute->id
                    );

                if (
                    ! $lockedSource
                    ||
                    ! $lockedTarget
                ) {
                    throw new RuntimeException(
                        'Source atau Target Route tidak ditemukan.'
                    );
                }

            /*
             * Status + approval dicek lagi
             * setelah mendapatkan row lock.
             */
                $this->assertEditable(
                    $lockedSource
                );

                $this->assertEditable(
                    $lockedTarget
                );

            /*
            |--------------------------------------------------------------------------
            | LOCK ALL SOURCE + TARGET STOPS
            |--------------------------------------------------------------------------
            */

                $lockedStops =
                    DeliveryStop::query()
                        ->whereIn(
                            'delivery_route_id',
                            [
                                $lockedSource->id,
                                $lockedTarget->id,
                            ]
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                $currentSourceStopUuids =
                    $lockedStops
                        ->where(
                            'delivery_route_id',
                            $lockedSource->id
                        )
                        ->pluck('uuid')
                        ->map(
                            fn ($uuid): string =>
                                (string) $uuid
                        )
                        ->sort()
                        ->values()
                        ->all();

                $currentTargetStopUuids =
                    $lockedStops
                        ->where(
                            'delivery_route_id',
                            $lockedTarget->id
                        )
                        ->pluck('uuid')
                        ->map(
                            fn ($uuid): string =>
                                (string) $uuid
                        )
                        ->sort()
                        ->values()
                        ->all();

            /*
             * Kalau ada admin lain mengubah route
             * ketika Python sedang bekerja,
             * jangan pakai hasil preview lama.
             */
                if (
                    $currentSourceStopUuids
                    !==
                    $sourceStopUuidsBefore
                ) {
                    throw new RuntimeException(
                        'Source Route berubah saat Smart Reroute diproses. Silakan ulangi.'
                    );
                }

                if (
                    $currentTargetStopUuids
                    !==
                    $targetStopUuidsBefore
                ) {
                    throw new RuntimeException(
                        'Target Route berubah saat Smart Reroute diproses. Silakan ulangi.'
                    );
                }

            /*
            |--------------------------------------------------------------------------
            | LOCK STOP YANG DIPINDAH
            |--------------------------------------------------------------------------
            */

                /** @var DeliveryStop|null $lockedStop */
                $lockedStop =
                    DeliveryStop::query()
                        ->with('packages')
                        ->whereKey(
                            $stop->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $lockedStop) {
                    throw new RuntimeException(
                        'Delivery Stop tidak ditemukan.'
                    );
                }

                if (
                    (int)
                    $lockedStop
                        ->delivery_route_id
                    !==
                    (int)
                    $lockedSource
                        ->id
                ) {
                    throw new RuntimeException(
                        'Delivery Stop sudah tidak berada pada Source Route.'
                    );
                }

            /*
            |--------------------------------------------------------------------------
            | REVALIDATE BOX TYPE
            |--------------------------------------------------------------------------
            */

                $lockedStopBoxTypes =
                    $lockedStop
                        ->packages
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
                    $lockedStopBoxTypes->count()
                    !==
                    1
                    ||
                    (string)
                    $lockedStopBoxTypes->first()
                    !==
                    $stopBoxType
                ) {
                    throw new RuntimeException(
                        'Box Type Stop berubah saat Smart Reroute diproses.'
                    );
                }

            /*
            |--------------------------------------------------------------------------
            | MOVE STOP
            |--------------------------------------------------------------------------
            |
            | sequence sementara 0.
            |
            | Nanti applyPreview() langsung mengganti
            | menggunakan hasil OR-Tools.
            |
            */

                $lockedStop->forceFill([
                    'delivery_route_id' =>
                        $lockedTarget->id,

                    'sequence_order' =>
                        0,
                ]);

                $lockedStop->save();

            /*
            |--------------------------------------------------------------------------
            | APPLY SOURCE RESULT
            |--------------------------------------------------------------------------
            */

                $this->reoptimizer
                    ->applyPreview(
                        $lockedSource,
                        $sourceAfter
                    );

            /*
            |--------------------------------------------------------------------------
            | APPLY TARGET RESULT
            |--------------------------------------------------------------------------
            */

                $this->reoptimizer
                    ->applyPreview(
                        $lockedTarget,
                        $targetAfter
                    );

            /*
            |--------------------------------------------------------------------------
            | SOURCE EMPTY → CANCEL
            |--------------------------------------------------------------------------
            */

                $sourceRemainingStops =
                    DeliveryStop::query()
                        ->where(
                            'delivery_route_id',
                            $lockedSource->id
                        )
                        ->count();

                if (
                    $sourceRemainingStops === 0
                ) {

                    $lockedSource->forceFill([
                    'status' =>
                            RouteStatus
                                ::Cancelled,
                    ]);

                    $lockedSource->save();
                }
            }
        );

    /*
    |--------------------------------------------------------------------------
    | CLEAN INTERNAL SNAPSHOT
    |--------------------------------------------------------------------------
    */

        foreach (
            [
                &$sourceAfter,
                &$targetAfter,
            ]
            as
            &$preview
        ) {

            unset(
                $preview[
                    '_snapshot_stop_uuids'
                ],

                $preview[
                    '_snapshot_package_ids'
                ]
            );
        }

        unset(
            $preview
        );

    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */

        return [
            'success' =>
                true,

            'stop_id' =>
                (int)
                $stop->id,

            'store_name' =>
                $stop
                    ->store
                    ?->name,

            'source_route_id' =>
                (int)
                $sourceRoute->id,

            'target_route_id' =>
                (int)
                $targetRoute->id,

            'source_cancelled' =>
                $sourceAfter[
                    'stop_count'
                ]
                ===
                0,

            'target_volume_after_m3' =>
                round(
                    $targetVolumeAfterM3,
                    6
                ),

            'target_capacity_m3' =>
                round(
                    $targetCapacityM3,
                    6
                ),

            'target_capacity_after_percent' =>
                round(
                    (
                        $targetVolumeAfterM3
                        /
                        $targetCapacityM3
                    )
                    * 100,
                    2
                ),

            'source_after' =>
                $sourceAfter,

            'target_after' =>
                $targetAfter,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD SOURCE ROUTE
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


        /** @var DeliveryRoute $route */
        $route =
            DeliveryRoute::query()
                ->with([
                    'branch',

                    'driver',

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


        return $route;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD STOP
    |--------------------------------------------------------------------------
    */

    protected function loadSourceStop(
        DeliveryRoute $sourceRoute,
        DeliveryStop|int $stop
    ): DeliveryStop {

        $stopId =
            $stop
            instanceof DeliveryStop

                ? $stop->id

                : $stop;


        /** @var DeliveryStop|null $loadedStop */
        $loadedStop =
            DeliveryStop::query()
                ->with([
                    'store',

                    'packages.order',

                    'packages.product',
                ])
                ->where(
                    'id',
                    $stopId
                )
                ->where(
                    'delivery_route_id',
                    $sourceRoute
                        ->id
                )
                ->first();


        if (! $loadedStop) {
            throw new RuntimeException(
                'Delivery Stop tidak ditemukan pada source route.'
            );
        }


        return $loadedStop;
    }


    /*
    |--------------------------------------------------------------------------
    | EDITABLE
    |--------------------------------------------------------------------------
    */

    protected function assertEditable(
        DeliveryRoute $route
    ): void {

        $status =
            $this->enumValue(
                $route->status
            );


        if (
            $status
            !==
            RouteStatus
                ::Planned
                ->value
        ) {
            throw new RuntimeException(
                'Smart Reroute hanya dapat digunakan pada route Planned.'
            );
        }


        if (
            $route
                ->approval_status
            !==
            'pending'
        ) {
            throw new RuntimeException(
                'Smart Reroute hanya dapat digunakan sebelum route di-Approve.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STOP BOX TYPE
    |--------------------------------------------------------------------------
    */

    protected function resolveStopBoxType(
        DeliveryStop $stop
    ): string {

        $boxTypes =
            $stop
                ->packages
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
                'Box type Delivery Stop tidak konsisten.'
            );
        }


        return
            (string)
            $boxTypes->first();
    }


    /*
    |--------------------------------------------------------------------------
    | STOP VOLUME
    |--------------------------------------------------------------------------
    */

    protected function calculateStopVolume(
        DeliveryStop $stop
    ): float {

        $volume =
            (float)
            $stop
                ->packages
                ->sum(
                    fn ($package): float =>
                        (float) (
                            $package
                                ->volume_m3
                            ?? 0
                        )
                );


        if (
            $volume <= 0
        ) {
            throw new RuntimeException(
                'Volume Delivery Stop tidak valid.'
            );
        }


        return $volume;
    }


    /*
    |--------------------------------------------------------------------------
    | ROUTE VOLUME
    |--------------------------------------------------------------------------
    */

    protected function calculateRouteVolume(
        Collection $stops
    ): float {

        return
            (float)
            $stops
                ->flatMap(
                    fn (
                        DeliveryStop $stop
                    ) =>
                        $stop
                            ->packages
                )
                ->sum(
                    fn ($package): float =>
                        (float) (
                            $package
                                ->volume_m3
                            ?? 0
                        )
                );
    }


    /*
    |--------------------------------------------------------------------------
    | RESULT HELPERS
    |--------------------------------------------------------------------------
    */

    protected function floatMetric(
        array $result,
        string $key
    ): float {

        return
            (float) (
                $result[
                    $key
                ]
                ?? 0
            );
    }


    protected function intMetric(
        array $result,
        string $key
    ): int {

        return
            (int) (
                $result[
                    $key
                ]
                ?? 0
            );
    }

/*
|--------------------------------------------------------------------------
| ROUTE DATE
|--------------------------------------------------------------------------
*/

protected function routeDateString(
    mixed $value
): string {

    if (
        $value
        instanceof
        \DateTimeInterface
    ) {
        return
            $value->format(
                'Y-m-d'
            );
    }


    $value =
        trim(
            (string)
            $value
        );


    return
        substr(
            $value,
            0,
            10
        );
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
            instanceof BackedEnum
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