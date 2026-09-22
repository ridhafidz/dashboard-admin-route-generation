<?php

namespace App\Filament\Resources\DeliveryRoutes\Pages;

use App\Enums\OrderStatus;
use App\Enums\PackageStatus;
use App\Enums\RouteStatus;
use App\Filament\Resources\DeliveryRoutes\DeliveryRouteResource;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use BackedEnum;
use Filament\Actions\Action;
use App\Services\SmartRerouteService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class ViewDeliveryRoute extends ViewRecord
{
    protected static string $resource =
        DeliveryRouteResource::class;

    protected string $view =
        'filament.resources.delivery-routes.pages.view-delivery-route';

    public ?int $smartRerouteStopId = null;

    public ?string $smartRerouteStoreName = null;

    public array $smartRerouteCandidates = [];

    public array $smartRerouteRejected = [];

    public ?string $smartRerouteError = null;

    public function mount(
        int|string $record
    ): void {
        parent::mount($record);

        /*
         * Load seluruh data yang diperlukan
         * halaman Control Tower.
         */
        $this->getRecord()->load([
            'branch',
            'driver',
            'vehicle.vehicleType',

            'deliveryStops.store',

            'deliveryStops.packages.order',

            /*
             * Product sudah kita tambahkan
             * pada Package model sebelumnya.
             */
            'deliveryStops.packages.product',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return
            'Detail Delivery Route #'
            . $this->getRecord()->id;
    }

    protected function getHeaderActions(): array
    {
        return [

            Action::make('approveRoute')

                ->label('Approve Route')

                ->icon(
                    'heroicon-o-check-circle'
                )

                ->color('success')

                ->requiresConfirmation()

                ->modalHeading(
                    'Approve Delivery Route'
                )

                ->modalDescription(
                    'Setelah route disetujui, route akan tersedia untuk Driver. '
                .   'Pastikan urutan dan tujuan pengiriman sudah sesuai.'
                )

                ->modalSubmitActionLabel(
                    'Ya, Approve Route'
                )

            /*
             * Hanya Planned + Pending
             * yang boleh di-approve.
             */
                ->visible(
                    function (): bool {

                        $status =
                            $this->record->status
                            instanceof \BackedEnum

                                ? $this->record
                                    ->status
                                    ->value

                                : (string)
                                    $this->record
                                        ->status;

                        return
                            $status === 'planned'
                            &&
                            $this->record
                                ->approval_status
                                ===
                                'pending';
                    }
                )

                ->action(
                    function (): void {

                        DB::transaction(
                            function (): void {

                                $route =
                                    DeliveryRoute::query()
                                        ->whereKey(
                                            $this->record
                                                ->getKey()
                                        )
                                        ->lockForUpdate()
                                        ->firstOrFail();

                                $status =
                                    $route->status
                                    instanceof \BackedEnum

                                        ? $route
                                            ->status
                                            ->value

                                        : (string)
                                            $route
                                                ->status;


                            /*
                             * Double protection.
                             */
                                if (
                                    $status
                                    !==
                                    'planned'
                                ) {
                                    throw new \RuntimeException(
                                        'Hanya route Planned yang dapat disetujui.'
                                    );
                                }


                                if (
                                    $route
                                    ->approval_status
                                    !==
                                    'pending'
                                ) {
                                    throw new \RuntimeException(
                                        'Route ini sudah diproses approval.'
                                    );
                                }


                                $route->forceFill([
                                    'approval_status' =>
                                        'approved',

                                    'approved_at' =>
                                        now(),

                                    'approved_by' =>
                                        auth()->id(),
                                ]);


                                $route->save();
                            }
                        );


                        $this->record->refresh();


                        Notification::make()
                            ->title(
                                'Route Berhasil Di-approve'
                            )
                            ->body(
                                'Route sekarang sudah tersedia untuk Driver.'
                            )
                            ->success()
                            ->send();
                    }
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LIVEWIRE ACTIONS — RESCHEDULE STOP
    |--------------------------------------------------------------------------
    |
    | Digunakan oleh tombol per-stop di Blade view.
    |
    */

    public function mountActionRescheduleStop(
        int $stopId
    ): void {
        $this->js("window.__rescheduleStopId = {$stopId}");
    }

    /**
     * Reschedule satu delivery stop ke tanggal lain.
     *
     * Alur:
     * 1. Detach semua package dari stop
     * 2. Package status → pending
     * 3. Order scheduled_date → tanggal baru
     * 4. Hapus stop dari route
     * 5. Jika route tidak punya stop lagi → cancel route
     */
    public function rescheduleStop(
        int $stopId,
        string $newDate
    ): void {
        /** @var DeliveryRoute $route */
        $route = $this->getRecord();

        if ($route->approval_status !== 'pending') {
            Notification::make()
                ->title('Route sudah Approved dan tidak dapat diubah.')
                ->danger()
                ->send();
            return;
        }

        $routeStatusValue =
            $route->status instanceof BackedEnum
                ? $route->status->value
                : (string) $route->status;

        if ($routeStatusValue !== 'planned') {
            Notification::make()
                ->title('Hanya route Planned yang dapat dimodifikasi.')
                ->danger()
                ->send();
            return;
        }

        $stop = DeliveryStop::query()
            ->where('id', $stopId)
            ->where('delivery_route_id', $route->id)
            ->first();

        if (! $stop) {
            Notification::make()
                ->title('Stop tidak ditemukan.')
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function () use ($stop, $route, $newDate): void {

            /*
             * Kumpulkan package_id dan order_id sebelum detach.
             */
            $packages = $stop->packages()->get();

            $orderIds = $packages
                ->pluck('order_id')
                ->unique()
                ->filter();

            /*
             * Detach packages dari stop.
             */
            $stop->packages()->detach();

            /*
             * Kembalikan package ke Pending.
             */
            foreach ($packages as $package) {
                $package->forceFill([
                    'status'         => PackageStatus::Pending,
                ])->save();
            }

            /*
             * Update scheduled_date di orders terkait.
             */
            foreach ($orderIds as $orderId) {
                \App\Models\Order::query()
                    ->where('id', $orderId)
                    ->update([
                        'scheduled_date' => $newDate,
                        'status'         => OrderStatus::Pending->value,
                    ]);
            }

            /*
             * Hapus stop.
             */
            $stop->delete();

            /*
             * Jika route tidak punya stop lagi, cancel route.
             */
            $remainingStops =
                DeliveryStop::query()
                    ->where('delivery_route_id', $route->id)
                    ->count();

            if ($remainingStops === 0) {
                $route->forceFill([
                    'status' => RouteStatus::Cancelled,
                ])->save();
            }
        });

        Notification::make()
            ->title("Stop dijadwalkan ulang ke {$newDate}.")
            ->success()
            ->send();

        $this->redirect(
            DeliveryRouteResource::getUrl(
                'view',
                ['record' => $route->id]
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LIVEWIRE ACTIONS — MOVE STOP TO ANOTHER ROUTE
    |--------------------------------------------------------------------------
    */

    /**
     * Pindahkan stop ke route lain (same branch, date, box type compatible).
     *
     * Alur:
     * 1. Validasi target route
     * 2. Update delivery_route_id stop
     * 3. Re-sequence kedua route (sequence sederhana: append di akhir target)
     * 4. Jika source route tidak punya stop lagi → cancel
     */
    public function moveStop(
    int $stopId,
    int $targetRouteId
): void {

    try {

        $result =
            app(
                SmartRerouteService::class
            )
                ->executeReroute(
                    sourceRoute:
                        $this->getRecord(),

                    stop:
                        $stopId,

                    targetRoute:
                        $targetRouteId
                );


        $targetAfter =
            $result[
                'target_after'
            ];


        $movedStop =
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
                        (
                            $row[
                                'store_name'
                            ]
                            ?? null
                        )
                        ===
                        $result[
                            'store_name'
                        ]
                );


        $sequence =
            $movedStop[
                'sequence'
            ]
            ?? '-';


        $arrival =
            $movedStop[
                'arrival_time'
            ]
            ?? '-';


        Notification::make()
            ->title(
                'Smart Reroute Berhasil'
            )
            ->body(
                "{$result['store_name']} dipindahkan "
                . "ke Route #{$result['target_route_id']}. "
                . "Sequence baru: {$sequence}, "
                . "ETA: {$arrival}. "
                . "Capacity: "
                . "{$result['target_capacity_after_percent']}%."
            )
            ->success()
            ->send();


        /*
         * Setelah reroute kita langsung buka
         * TARGET route supaya Admin bisa review
         * hasil sequence + ETA barunya.
         */
        $this->redirect(
            DeliveryRouteResource::getUrl(
                'view',
                [
                    'record' =>
                        $result[
                            'target_route_id'
                        ],
                ]
            )
        );

    } catch (
        \Throwable $e
    ) {

        Notification::make()
            ->title(
                'Smart Reroute Gagal'
            )
            ->body(
                $e->getMessage()
            )
            ->danger()
            ->send();
    }
}

/*
|--------------------------------------------------------------------------
| SMART REROUTE — LOAD RECOMMENDATIONS
|--------------------------------------------------------------------------
|
| Dipanggil hanya ketika Admin klik tombol Smart Reroute.
|
| Jadi page load normal TIDAK memanggil:
| - Google Matrix
| - Python
| - OR-Tools
|
*/

public function loadSmartRerouteRecommendations(
    int $stopId
): void {

    /*
     * Reset hasil sebelumnya.
     */
    $this->smartRerouteStopId =
        $stopId;

    $this->smartRerouteStoreName =
        null;

    $this->smartRerouteCandidates =
        [];

    $this->smartRerouteRejected =
        [];

    $this->smartRerouteError =
        null;


    try {

        $result =
            app(
                SmartRerouteService::class
            )
                ->recommendations(
                    $this->getRecord(),
                    $stopId
                );


        $this->smartRerouteStoreName =
            $result[
                'store_name'
            ]
            ?? '-';


        /*
         * Jangan simpan source_after / target_after
         * yang besar ke Livewire state.
         *
         * UI hanya membutuhkan summary.
         */
        $this->smartRerouteCandidates =
            collect(
                $result[
                    'candidates'
                ]
                ?? []
            )
                ->map(
                    function (
                        array $row
                    ): array {

                        return [

                            'rank' =>
                                (int) (
                                    $row[
                                        'rank'
                                    ]
                                    ?? 0
                                ),

                            'target_route_id' =>
                                (int)
                                $row[
                                    'target_route_id'
                                ],

                            'driver_name' =>
                                $row[
                                    'driver_name'
                                ]
                                ?? '-',

                            'vehicle_plate' =>
                                $row[
                                    'vehicle_plate'
                                ]
                                ?? '-',

                            'vehicle_category' =>
                                $row[
                                    'vehicle_category'
                                ]
                                ?? '-',

                            'box_type' =>
                                $row[
                                    'box_type'
                                ]
                                ?? '-',

                            'suggested_sequence' =>
                                $row[
                                    'suggested_sequence'
                                ]
                                ?? null,

                            'suggested_arrival_time' =>
                                $row[
                                    'suggested_arrival_time'
                                ]
                                ?? null,

                            /*
                             * Dampak ke route target.
                             */
                            'target_extra_distance_km' =>
                                (float) (
                                    $row[
                                        'target_extra_distance_km'
                                    ]
                                    ?? 0
                                ),

                            'target_extra_duration_minutes' =>
                                (int) (
                                    $row[
                                        'target_extra_duration_minutes'
                                    ]
                                    ?? 0
                                ),

                            /*
                             * Dampak gabungan source + target.
                             */
                            'net_distance_delta_km' =>
                                (float) (
                                    $row[
                                        'net_distance_delta_km'
                                    ]
                                    ?? 0
                                ),

                            'net_duration_delta_minutes' =>
                                (int) (
                                    $row[
                                        'net_duration_delta_minutes'
                                    ]
                                    ?? 0
                                ),

                            /*
                             * Capacity target setelah move.
                             */
                            'capacity_utilization_after_percent' =>
                                (float) (
                                    $row[
                                        'capacity_utilization_after_percent'
                                    ]
                                    ?? 0
                                ),

                            /*
                             * Comparison.
                             */
                            'combined_distance_before_km' =>
                                (float) (
                                    $row[
                                        'combined_distance_before_km'
                                    ]
                                    ?? 0
                                ),

                            'combined_distance_after_km' =>
                                (float) (
                                    $row[
                                        'combined_distance_after_km'
                                    ]
                                    ?? 0
                                ),

                            'combined_duration_before_minutes' =>
                                (int) (
                                    $row[
                                        'combined_duration_before_minutes'
                                    ]
                                    ?? 0
                                ),

                            'combined_duration_after_minutes' =>
                                (int) (
                                    $row[
                                        'combined_duration_after_minutes'
                                    ]
                                    ?? 0
                                ),
                        ];
                    }
                )
                ->values()
                ->all();


        $this->smartRerouteRejected =
            collect(
                $result[
                    'rejected'
                ]
                ?? []
            )
                ->map(
                    fn (
                        array $row
                    ): array => [

                        'target_route_id' =>
                            $row[
                                'target_route_id'
                            ]
                            ?? null,

                        'driver_name' =>
                            $row[
                                'driver_name'
                            ]
                            ?? '-',

                        'vehicle_plate' =>
                            $row[
                                'vehicle_plate'
                            ]
                            ?? '-',

                        'reason' =>
                            $row[
                                'reason'
                            ]
                            ?? 'Tidak eligible.',
                    ]
                )
                ->values()
                ->all();

    } catch (
        \Throwable $e
    ) {

        $this->smartRerouteError =
            $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| SMART REROUTE — EXECUTE
|--------------------------------------------------------------------------
*/

public function executeSmartReroute(
    int $targetRouteId
): void {

    if (
        ! $this->smartRerouteStopId
    ) {

        Notification::make()
            ->title(
                'Delivery Stop belum dipilih.'
            )
            ->danger()
            ->send();

        return;
    }


    /*
     * moveStop() sekarang sudah menggunakan
     * SmartRerouteService::executeReroute().
     */
    $this->moveStop(
        $this->smartRerouteStopId,
        $targetRouteId
    );
}

    /*
    |--------------------------------------------------------------------------
    | VIEW DATA
    |--------------------------------------------------------------------------
    */

    protected function getViewData(): array
    {
        /** @var DeliveryRoute $route */
        $route =
            $this->getRecord();

        $routeStatusValue =
            $route->status instanceof BackedEnum
                ? $route->status->value
                : (string) $route->status;

        $isPending  = $route->approval_status === 'pending';
        $isApproved = $route->approval_status === 'approved';
        $isPlanned  = $routeStatusValue === 'planned';

        /*
         * Nama user yang approve.
         */
        $approvedByName = null;
        if ($route->approved_by) {
            $approver = User::query()
                ->find($route->approved_by);
            $approvedByName = $approver?->name;
        }

        /*
         * Route lain yang tersedia sebagai target Move
         * (same branch, date, pending, planned, exclude self).
         */
        $moveTargetRoutes =
            DeliveryRoute::query()
                ->with('vehicle.vehicleType', 'driver')
                ->where('branch_id', $route->branch_id)
                ->where('approval_status', 'pending')
                ->where('status', RouteStatus::Planned->value)
                ->whereDate('route_date', $route->route_date)
                ->where('id', '!=', $route->id)
                ->get()
                ->map(fn (DeliveryRoute $r): array => [
                    'id'         => $r->id,
                    'label'      => "Route #{$r->id} — {$r->driver?->name} / {$r->vehicle?->plate_number}",
                    'box_type'   => $this->enumValue(
                        $r->vehicle?->vehicleType?->box_type
                    ),
                ])
                ->values()
                ->all();

        /*
        |--------------------------------------------------------------------------
        | STOP
        |--------------------------------------------------------------------------
        */

        $stops =
            $route
                ->deliveryStops
                ->sortBy(
                    'sequence_order'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | PACKAGE UNIQUE
        |--------------------------------------------------------------------------
        */

        $packages =
            $stops
                ->flatMap(
                    fn ($stop) =>
                        $stop->packages
                )
                ->unique('id')
                ->values();

        /*
        |--------------------------------------------------------------------------
        | LOAD SUMMARY
        |--------------------------------------------------------------------------
        */

        $totalVolumeM3 =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->volume_m3
                        ?? 0
                    )
            );

        $totalWeightKg =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->weight_kg
                        ?? 0
                    )
            );

        $totalPrice =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->total_price
                        ?? 0
                    )
            );

        $capacityVolumeM3 =
            (float) (
                $route
                    ->vehicle
                    ?->vehicleType
                    ?->volume_m3
                ?? 0
            );

        $volumeUtilization =
            $capacityVolumeM3 > 0
                ? (
                    $totalVolumeM3
                    /
                    $capacityVolumeM3
                ) * 100
                : 0.0;

        /*
        |--------------------------------------------------------------------------
        | STOP DETAIL
        |--------------------------------------------------------------------------
        */

        $stopRows =
            $stops
                ->map(
                    function ($stop) use ($isPending, $isPlanned, $moveTargetRoutes): array {

                        /*
                         * Box type stop = box type dari packages pertamanya.
                         * Dipakai untuk filter target route saat Move.
                         */
                        $stopBoxType = $this->enumValue(
                            $stop->packages->first()?->box_type
                        );

                        /*
                         * Filter moveTargetRoutes berdasarkan box type stop.
                         */
                        $eligibleMoveTargets = array_values(
                            array_filter(
                                $moveTargetRoutes,
                                fn (array $r): bool =>
                                    $r['box_type'] === $stopBoxType
                            )
                        );

                        return [

                            'id' =>
                                $stop->id,

                            'sequence' =>
                                (int)
                                $stop
                                    ->sequence_order,

                            'status' =>
                                $this->enumLabel(
                                    $stop->status
                                ),

                            'status_value' =>
                                $this->enumValue(
                                    $stop->status
                                ),

                            'store' => [

                                'id' =>
                                    $stop
                                        ->store
                                        ?->id,

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
                                        ?->latitude
                                    !== null

                                        ? (float)
                                            $stop
                                                ->store
                                                ->latitude

                                        : null,

                                'longitude' =>
                                    $stop
                                        ->store
                                        ?->longitude
                                    !== null

                                        ? (float)
                                            $stop
                                                ->store
                                                ->longitude

                                        : null,
                            ],

                            'predicted_arrival' =>
                                $stop
                                    ->predicted_arrival_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'service_start' =>
                                $stop
                                    ->predicted_service_start_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'service_end' =>
                                $stop
                                    ->predicted_service_end_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'waiting_minutes' =>
                                (int) (
                                    $stop
                                        ->predicted_waiting_minutes
                                    ?? 0
                                ),

                            /*
                             * Flag apakah tombol aksi boleh ditampilkan.
                             */
                            'can_act' =>
                                $isPending && $isPlanned,

                            'box_type' =>
                                $stopBoxType,

                            'move_targets' =>
                                $eligibleMoveTargets,

                            'packages' =>
                                $stop
                                    ->packages
                                    ->map(
                                        function (
                                            $package
                                        ): array {

                                            return [

                                                'tracking_number' =>
                                                    $package
                                                        ->tracking_number,

                                                'order_number' =>
                                                    $package
                                                        ->order
                                                        ?->order_number,

                                                'product_code' =>
                                                    $package
                                                        ->product
                                                        ?->code,

                                                'product_name' =>
                                                    $package
                                                        ->item,

                                                'quantity' =>
                                                    (int)
                                                    $package
                                                        ->quantity,

                                                'uom' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->uom
                                                            ?? null
                                                        ),

                                                'box_type' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->box_type
                                                            ?? null
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

                                                'total_price' =>
                                                    $package
                                                        ->total_price
                                                    !== null

                                                        ? (float)
                                                            $package
                                                                ->total_price

                                                        : null,

                                                'status' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->status
                                                        ),
                                            ];
                                        }
                                    )
                                    ->values()
                                    ->all(),
                        ];
                    }
                )
                ->all();

        /*
        |--------------------------------------------------------------------------
        | GOOGLE MAP PAYLOAD
        |--------------------------------------------------------------------------
        */

        $mapData = [

            'route_id' =>
                $route->id,

            'branch' => [

                'name' =>
                    $route
                        ->branch
                        ?->name,

                'code' =>
                    $route
                        ->branch
                        ?->init_cab,

                'latitude' =>
                    $route
                        ->branch
                        ?->latitude
                    !== null

                        ? (float)
                            $route
                                ->branch
                                ->latitude

                        : null,

                'longitude' =>
                    $route
                        ->branch
                        ?->longitude
                    !== null

                        ? (float)
                            $route
                                ->branch
                                ->longitude

                        : null,
            ],

            'stops' =>
                collect(
                    $stopRows
                )
                    ->filter(
                        fn (
                            array $stop
                        ): bool =>
                            $stop[
                                'store'
                            ][
                                'latitude'
                            ] !== null

                            &&

                            $stop[
                                'store'
                            ][
                                'longitude'
                            ] !== null
                    )
                    ->values()
                    ->all(),
        ];

        return [

            'route' =>
                $route,

            'routeStatusLabel' =>
                $this->enumLabel(
                    $route->status
                ),

            'vehicleCategoryLabel' =>
                $this->enumLabel(
                    $route
                        ->vehicle
                        ?->vehicleType
                        ?->category
                ),

            'boxTypeLabel' =>
                $this->enumLabel(
                    $route
                        ->vehicle
                        ?->vehicleType
                        ?->box_type
                ),

            'predictedDurationLabel' =>
                $this->durationLabel(
                    (int) (
                        $route
                            ->predicted_duration_minutes
                        ?? 0
                    )
                ),

            'stops' =>
                $stopRows,

            'packageCount' =>
                $packages->count(),

            'totalVolumeM3' =>
                $totalVolumeM3,

            'totalWeightKg' =>
                $totalWeightKg,

            'totalPrice' =>
                $totalPrice,

            'capacityVolumeM3' =>
                $capacityVolumeM3,

            'volumeUtilization' =>
                $volumeUtilization,

            'mapData' =>
                $mapData,

            'googleMapsBrowserKey' =>
                config(
                    'services.google_maps.browser_key'
                ),

            'googleMapsMapId' =>
                config(
                    'services.google_maps.map_id',
                    'DEMO_MAP_ID'
                ),

            /*
            |------------------------------------------------------------------
            | APPROVAL
            |------------------------------------------------------------------
            */

            'approvalStatus' =>
                $route->approval_status ?? 'pending',

            'approvalStatusLabel' =>
                match ($route->approval_status) {
                    'approved' => 'Approved',
                    'pending'  => 'Pending Approval',
                    default    => ucfirst((string) ($route->approval_status ?? 'pending')),
                },

            'approvedByName' =>
                $approvedByName,

            'approvedAt' =>
                $route->approved_at
                    ?->format('d M Y · H:i'),

            'isPending'  => $isPending,
            'isApproved' => $isApproved,
            'isPlanned'  => $isPlanned,
            'canApprove' => $isPending && $isPlanned,
            'canRevoke'  => $isApproved && $isPlanned,
        ];
    }

    private function durationLabel(
        int $minutes
    ): string {

        $minutes =
            max(
                0,
                $minutes
            );

        $hours =
            intdiv(
                $minutes,
                60
            );

        $remainingMinutes =
            $minutes % 60;

        if ($hours <= 0) {

            return
                $remainingMinutes
                . ' menit';
        }

        return
            $hours
            . ' jam '
            . $remainingMinutes
            . ' menit';
    }

    private function enumValue(
        mixed $value
    ): ?string {

        if (
            $value instanceof
            BackedEnum
        ) {
            return
                (string)
                $value->value;
        }

        if ($value === null) {
            return null;
        }

        return
            (string)
            $value;
    }

    private function enumLabel(
        mixed $value
    ): string {

        if ($value === null) {
            return '-';
        }

        if (
            is_object($value)
            &&
            method_exists(
                $value,
                'getLabel'
            )
        ) {

            return
                (string)
                $value->getLabel();
        }

        if (
            $value instanceof
            BackedEnum
        ) {

            return
                str(
                    (string)
                    $value->value
                )
                    ->replace(
                        '_',
                        ' '
                    )
                    ->title()
                    ->toString();
        }

        return
            str(
                (string)
                $value
            )
                ->replace(
                    '_',
                    ' '
                )
                ->title()
                ->toString();
    }
}