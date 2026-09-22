<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\DriverAttendance;
use App\Models\Views\DriverTrackingCurrent;
use App\Services\BranchContext;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class DriverTracking extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup =
        'Operasional';

    protected static ?string $navigationLabel =
        'Driver Tracking';

    protected static ?string $title =
        'Driver Tracking';

    protected static ?int $navigationSort =
        30;

    protected string $view =
        'filament.pages.driver-tracking';

    public array $trackingData = [];


    /*
    |--------------------------------------------------------------------------
    | INITIAL LOAD
    |--------------------------------------------------------------------------
    |
    | Full data hanya dibangun sekali ketika halaman pertama dibuka.
    |
    */

    public function mount(): void
    {
        $this->trackingData =
            $this->buildTrackingData();
    }


    /*
    |--------------------------------------------------------------------------
    | LIVE REFRESH
    |--------------------------------------------------------------------------
    |
    | Polling TIDAK lagi:
    |
    | - memanggil mount()
    | - reload milestone
    | - reload package
    | - reload seluruh delivery_logs
    | - render ulang Blade
    |
    | Hanya mengambil data posisi/status yang berubah.
    |
    */

    public function refreshTracking(): void
    {
        $branchId =
            app(
                BranchContext::class
            )->getId();


        if (! $branchId) {

            $this->dispatch(
                'driver-tracking-updated',
                driverUpdates: []
            );

            $this->skipRender();

            return;
        }


        $driverUpdates =
            $this->buildLiveDriverUpdates(
                $branchId
            );


        $this->dispatch(
            'driver-tracking-updated',
            driverUpdates:
                $driverUpdates
        );


        /*
         * Jangan render ulang Blade.
         *
         * Google Maps + legend tetap hidup di browser.
         */
        $this->skipRender();
    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL TRACKING DATA
    |--------------------------------------------------------------------------
    */

    protected function buildTrackingData(): array
    {
        $branchId =
            app(
                BranchContext::class
            )->getId();


        if (! $branchId) {

            return [
                'branch' => null,
                'drivers' => [],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | BRANCH
        |--------------------------------------------------------------------------
        */

        $branch =
            Branch::query()
                ->select([
                    'id',
                    'uuid',
                    'init_cab',
                    'name',
                    'latitude',
                    'longitude',
                ])
                ->find(
                    $branchId
                );


        /*
        |--------------------------------------------------------------------------
        | DRIVER CABANG
        |--------------------------------------------------------------------------
        */

        $drivers =
            Driver::query()
                ->select([
                    'id',
                    'uuid',
                    'branch_id',
                    'name',
                    'status',
                ])
                ->where(
                    'branch_id',
                    $branchId
                )
                ->orderBy(
                    'name'
                )
                ->get();


        if ($drivers->isEmpty()) {

            return [
                'branch' =>
                    $this->branchData(
                        $branch
                    ),

                'drivers' => [],
            ];
        }


        $driverIds =
            $drivers
                ->pluck('id');


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE
        |--------------------------------------------------------------------------
        |
        | 1 query untuk seluruh driver.
        |
        | Tidak lagi 1 query per driver.
        |
        */

        $attendances =
            $this
                ->getLatestAttendances(
                    $driverIds
                );


        /*
        |--------------------------------------------------------------------------
        | CURRENT TRACKING VIEW
        |--------------------------------------------------------------------------
        |
        | VIEW menentukan:
        |
        | - route driver hari ini
        | - vehicle
        | - route status
        | - latest GPS
        |
        */

        $trackingRows =
            $this
                ->getCurrentTrackingRows(
                    $branchId
                );


        /*
        |--------------------------------------------------------------------------
        | ROUTE IDs
        |--------------------------------------------------------------------------
        */

        $routeIds =
            $trackingRows
                ->pluck(
                    'delivery_route_id'
                )
                ->filter()
                ->unique()
                ->values();


        /*
        |--------------------------------------------------------------------------
        | ROUTE MILESTONE
        |--------------------------------------------------------------------------
        |
        | Ini hanya dijalankan INITIAL LOAD.
        |
        | Penting:
        |
        | gps_ping TIDAK di-load ke Eloquent.
        |
        | Ribuan GPS ping tidak diperlukan untuk menggambar milestone.
        |
        */

        $routes =
            $routeIds->isEmpty()

                ? collect()

                : DeliveryRoute::query()

                    ->with([

                        /*
                         * Hanya event yang benar-benar
                         * menjadi milestone map.
                         */
                        'deliveryLogs' =>
                            function ($query): void {

                                $query
                                    ->whereIn(
                                        'event_type',
                                        [
                                            'departure',
                                            'arrival',
                                            'checkpoint',
                                        ]
                                    )
                                    ->orderBy(
                                        'recorded_at'
                                    );
                            },


                        'deliveryLogs.deliveryStop.store',

                        'deliveryLogs.deliveryStop.packages.order',
                    ])

                    ->whereIn(
                        'id',
                        $routeIds
                    )

                    ->get()

                    ->keyBy(
                        'driver_id'
                    );


        /*
        |--------------------------------------------------------------------------
        | DRIVER COLOR
        |--------------------------------------------------------------------------
        */

        $palette = [
            '#2563eb',
            '#16a34a',
            '#f59e0b',
            '#dc2626',
            '#7c3aed',
            '#0891b2',
            '#db2777',
            '#4f46e5',
        ];


        $driverRows = [];


        foreach (
            $drivers
            as
            $index => $driver
        ) {

            $attendance =
                $attendances
                    ->get(
                        $driver->id
                    );


            $trackingRow =
                $trackingRows
                    ->get(
                        $driver->id
                    );


            $route =
                $routes
                    ->get(
                        $driver->id
                    );


            $points = [];


            /*
            |--------------------------------------------------------------------------
            | CHECK-IN
            |--------------------------------------------------------------------------
            */

            if (
                $attendance
                &&
                $attendance
                    ->checkin_latitude
                !== null
                &&
                $attendance
                    ->checkin_longitude
                !== null
            ) {

                $points[] = [

                    'type' =>
                        'checkin',

                    'title' =>
                        'Check In',

                    'latitude' =>
                        (float)
                        $attendance
                            ->checkin_latitude,

                    'longitude' =>
                        (float)
                        $attendance
                            ->checkin_longitude,

                    'recorded_at' =>
                        $attendance
                            ->checkin_at
                            ?->format(
                                'd M Y H:i:s'
                            ),

                    'store_name' =>
                        null,

                    'photo_url' =>
                        $this->photoUrl(
                            $attendance
                                ->checkin_photo
                        ),

                    'packages' =>
                        [],
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | DELIVERY MILESTONE
            |--------------------------------------------------------------------------
            */

            if ($route) {

                foreach (
                    $route->deliveryLogs
                    as
                    $log
                ) {

                    if (
                        $log->latitude
                        === null
                        ||
                        $log->longitude
                        === null
                    ) {

                        continue;
                    }


                    $stop =
                        $log
                            ->deliveryStop;


                    $packages =
                        $stop
                            ?->packages
                            ?->map(
                                function (
                                    $package
                                ): array {

                                    return [

                                        'order_number' =>
                                            $package
                                                ->order
                                                ?->order_number,

                                        /*
                                         * Package item sudah menyimpan
                                         * nama produk.
                                         *
                                         * Jadi tidak perlu eager-load
                                         * relation product.
                                         */
                                        'product_name' =>
                                            $package
                                                ->item,

                                        'quantity' =>
                                            $package
                                                ->quantity,

                                        'uom' =>
                                            $this
                                                ->enumValue(
                                                    $package
                                                        ->uom
                                                ),
                                    ];
                                }
                            )
                            ?->values()
                            ?->all()

                        ?? [];


                    $points[] = [

                        'type' =>
                            $log
                                ->event_type,

                        'title' =>
                            match (
                                $log
                                    ->event_type
                            ) {

                                'departure' =>
                                    'Berangkat',

                                'arrival' =>
                                    'Tiba di Store',

                                'checkpoint' =>
                                    'Checkpoint',

                                default =>
                                    ucfirst(
                                        (string)
                                        $log
                                            ->event_type
                                    ),
                            },

                        'latitude' =>
                            (float)
                            $log
                                ->latitude,

                        'longitude' =>
                            (float)
                            $log
                                ->longitude,

                        'recorded_at' =>
                            $log
                                ->recorded_at
                                ?->format(
                                    'd M Y H:i:s'
                                ),

                        'store_name' =>
                            $stop
                                ?->store
                                ?->name,

                        'photo_url' =>
                            $this->photoUrl(
                                $stop
                                    ?->proof_of_delivery
                            ),

                        'packages' =>
                            $packages,
                    ];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CURRENT LOCATION
            |--------------------------------------------------------------------------
            |
            | Prioritas:
            |
            | 1. latest GPS dari VIEW
            | 2. latest milestone
            | 3. check-in
            |
            */

            $currentLocation =
                $this->resolveCurrentLocation(
                    $trackingRow,
                    $route,
                    $attendance
                );


            /*
            |--------------------------------------------------------------------------
            | DRIVER RESULT
            |--------------------------------------------------------------------------
            */

            $driverRows[] = [

                'id' =>
                    $driver
                        ->uuid,

                'name' =>
                    $driver
                        ->name,

                'status' =>
                    $this
                        ->enumValue(
                            $driver
                                ->status
                        )
                    ?? '-',

                'route_id' =>
                    $route
                        ?->uuid,

                'route_status' =>
                    $this
                        ->enumValue(
                            $route
                                ?->status
                        )
                    ??
                    $trackingRow
                        ?->route_status,

                /*
                 * Plate langsung dari VIEW.
                 *
                 * Tidak perlu relation vehicle.
                 */
                'plate_number' =>
                    $trackingRow
                        ?->plate_number,

                'color' =>
                    $palette[
                        $index
                        %
                        count(
                            $palette
                        )
                    ],

                'current_location' =>
                    $currentLocation,

                'points' =>
                    $points,
            ];
        }


        return [

            'branch' =>
                $this->branchData(
                    $branch
                ),

            'drivers' =>
                $driverRows,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | LIVE DRIVER UPDATE
    |--------------------------------------------------------------------------
    |
    | Sangat ringan dibanding buildTrackingData().
    |
    | Tidak menyentuh:
    |
    | delivery stop
    | package
    | order
    | store
    | route polyline
    |
    */

    protected function buildLiveDriverUpdates(
        int $branchId
    ): array {

        $drivers =
            Driver::query()
                ->select([
                    'id',
                    'uuid',
                    'name',
                    'status',
                ])
                ->where(
                    'branch_id',
                    $branchId
                )
                ->orderBy(
                    'name'
                )
                ->get();


        if ($drivers->isEmpty()) {

            return [];
        }


        $driverIds =
            $drivers
                ->pluck('id');


        $attendances =
            $this
                ->getLatestAttendances(
                    $driverIds
                );


        $trackingRows =
            $this
                ->getCurrentTrackingRows(
                    $branchId
                );


        $palette = [
            '#2563eb',
            '#16a34a',
            '#f59e0b',
            '#dc2626',
            '#7c3aed',
            '#0891b2',
            '#db2777',
            '#4f46e5',
        ];


        return $drivers
            ->values()
            ->map(
                function (
                    Driver $driver,
                    int $index
                ) use (
                    $trackingRows,
                    $attendances,
                    $palette
                ): array {

                    $trackingRow =
                        $trackingRows
                            ->get(
                                $driver->id
                            );


                    $attendance =
                        $attendances
                            ->get(
                                $driver->id
                            );


                    /*
                     * Polling tidak perlu route object.
                     *
                     * Current GPS langsung dari VIEW.
                     */

                    if (
                        $trackingRow
                        &&
                        $trackingRow
                            ->current_latitude
                        !== null
                        &&
                        $trackingRow
                            ->current_longitude
                        !== null
                    ) {

                        $currentLocation = [

                            'latitude' =>
                                (float)
                                $trackingRow
                                    ->current_latitude,

                            'longitude' =>
                                (float)
                                $trackingRow
                                    ->current_longitude,

                            'recorded_at' =>
                                $trackingRow
                                    ->last_gps_at
                                    ?->format(
                                        'd M Y H:i:s'
                                    ),
                        ];

                    } elseif (
                        $attendance
                        &&
                        $attendance
                            ->checkin_latitude
                        !== null
                        &&
                        $attendance
                            ->checkin_longitude
                        !== null
                    ) {

                        $currentLocation = [

                            'latitude' =>
                                (float)
                                $attendance
                                    ->checkin_latitude,

                            'longitude' =>
                                (float)
                                $attendance
                                    ->checkin_longitude,

                            'recorded_at' =>
                                $attendance
                                    ->checkin_at
                                    ?->format(
                                        'd M Y H:i:s'
                                    ),
                        ];

                    } else {

                        $currentLocation =
                            null;
                    }


                    return [

                        'id' =>
                            $driver
                                ->uuid,

                        'name' =>
                            $driver
                                ->name,

                        'status' =>
                            $this
                                ->enumValue(
                                    $driver
                                        ->status
                                )
                            ?? '-',

                        /*
                         * Untuk popup hanya perlu tahu
                         * apakah assigned atau tidak.
                         */
                        'route_id' =>
                            $trackingRow
                                ?->delivery_route_id,

                        'route_status' =>
                            $trackingRow
                                ?->route_status,

                        'plate_number' =>
                            $trackingRow
                                ?->plate_number,

                        'latest_log_id' =>
                            $trackingRow
                                ?->latest_log_id,

                        'color' =>
                            $palette[
                                $index
                                %
                                count(
                                    $palette
                                )
                            ],

                        'current_location' =>
                            $currentLocation,
                    ];
                }
            )
            ->all();
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT TRACKING VIEW
    |--------------------------------------------------------------------------
    */

    protected function getCurrentTrackingRows(
        int $branchId
    ): Collection {

        return DriverTrackingCurrent::query()

            ->where(
                'branch_id',
                $branchId
            )

            /*
             * route_date adalah DATE.
             *
             * Tidak perlu whereDate().
             */
            ->where(
                'route_date',
                today()->toDateString()
            )

            ->where(
                'route_status',
                '!=',
                'cancelled'
            )

            /*
             * Jika secara tidak sengaja driver punya
             * lebih dari satu route hari ini,
             * gunakan route paling baru.
             */
            ->orderByDesc(
                'delivery_route_id'
            )

            ->get()

            ->unique(
                'driver_id'
            )

            ->keyBy(
                'driver_id'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE HARI INI
    |--------------------------------------------------------------------------
    |
    | Menggunakan range datetime supaya index:
    |
    | driver_id + checkin_at
    |
    | dapat dimanfaatkan lebih baik.
    |
    */

    protected function getLatestAttendances(
        Collection $driverIds
    ): Collection {

        if ($driverIds->isEmpty()) {

            return collect();
        }


        $start =
            today()
                ->startOfDay();

        $end =
            today()
                ->endOfDay();


        return DriverAttendance::query()

            ->select([
                'id',
                'driver_id',
                'checkin_at',
                'checkin_latitude',
                'checkin_longitude',
                'checkin_photo',
            ])

            ->whereIn(
                'driver_id',
                $driverIds
            )

            ->whereBetween(
                'checkin_at',
                [
                    $start,
                    $end,
                ]
            )

            ->orderByDesc(
                'checkin_at'
            )

            ->get()

            ->unique(
                'driver_id'
            )

            ->keyBy(
                'driver_id'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT LOCATION RESOLVER
    |--------------------------------------------------------------------------
    */

    protected function resolveCurrentLocation(
        mixed $trackingRow,
        ?DeliveryRoute $route,
        mixed $attendance
    ): ?array {

        /*
         * 1. GPS dari VIEW.
         */
        if (
            $trackingRow
            &&
            $trackingRow
                ->current_latitude
            !== null
            &&
            $trackingRow
                ->current_longitude
            !== null
        ) {

            return [

                'latitude' =>
                    (float)
                    $trackingRow
                        ->current_latitude,

                'longitude' =>
                    (float)
                    $trackingRow
                        ->current_longitude,

                'recorded_at' =>
                    $trackingRow
                        ->last_gps_at
                        ?->format(
                            'd M Y H:i:s'
                        ),
            ];
        }


        /*
         * 2. Milestone terakhir.
         *
         * GPS ping memang sengaja tidak dimuat
         * ke relation.
         */
        $lastMilestone =
            $route
                ?->deliveryLogs
                ?->filter(
                    fn ($log): bool =>

                        $log->latitude
                        !== null

                        &&

                        $log->longitude
                        !== null
                )
                ?->sortBy(
                    'recorded_at'
                )
                ?->last();


        if ($lastMilestone) {

            return [

                'latitude' =>
                    (float)
                    $lastMilestone
                        ->latitude,

                'longitude' =>
                    (float)
                    $lastMilestone
                        ->longitude,

                'recorded_at' =>
                    $lastMilestone
                        ->recorded_at
                        ?->format(
                            'd M Y H:i:s'
                        ),
            ];
        }


        /*
         * 3. Check-in.
         */
        if (
            $attendance
            &&
            $attendance
                ->checkin_latitude
            !== null
            &&
            $attendance
                ->checkin_longitude
            !== null
        ) {

            return [

                'latitude' =>
                    (float)
                    $attendance
                        ->checkin_latitude,

                'longitude' =>
                    (float)
                    $attendance
                        ->checkin_longitude,

                'recorded_at' =>
                    $attendance
                        ->checkin_at
                        ?->format(
                            'd M Y H:i:s'
                        ),
            ];
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | BRANCH RESPONSE
    |--------------------------------------------------------------------------
    */

    protected function branchData(
        ?Branch $branch
    ): ?array {

        if (! $branch) {

            return null;
        }


        return [

            'id' =>
                $branch
                    ->uuid,

            'code' =>
                $branch
                    ->init_cab,

            'name' =>
                $branch
                    ->name,

            'latitude' =>
                $branch
                    ->latitude
                !== null

                    ? (float)
                        $branch
                            ->latitude

                    : null,

            'longitude' =>
                $branch
                    ->longitude
                !== null

                    ? (float)
                        $branch
                            ->longitude

                    : null,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | PHOTO URL
    |--------------------------------------------------------------------------
    */

    protected function photoUrl(
        ?string $path
    ): ?string {

        if (! $path) {

            return null;
        }


        if (
            str_starts_with(
                $path,
                'http://'
            )

            ||

            str_starts_with(
                $path,
                'https://'
            )
        ) {

            return $path;
        }


        return Storage::disk(
            'public'
        )->url(
            $path
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ENUM NORMALIZER
    |--------------------------------------------------------------------------
    */

    private function enumValue(
        mixed $value
    ): ?string {

        if (
            $value
            instanceof
            BackedEnum
        ) {

            return
                (string)
                $value
                    ->value;
        }


        if ($value === null) {

            return null;
        }


        return
            (string)
            $value;
    }
}