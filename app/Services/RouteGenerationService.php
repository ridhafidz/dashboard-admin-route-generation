<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\OrderStatus;
use App\Enums\PackageStatus;
use App\Enums\RouteStatus;
use App\Enums\VehicleStatus;
use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Package;
use App\Models\Vehicle;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RouteGenerationService
{
    protected const DEFAULT_BRANCH_START_TIME = '08:00';

    public function generateTodayRoutes(array $data): array
    {
        $branchId = (int) ($data['branch_id'] ?? 0);

        if ($branchId <= 0) {
            throw new RuntimeException(
                'Cabang wajib dipilih.'
            );
        }

        $branch = Branch::query()
            ->findOrFail($branchId);

        if (
            $branch->latitude === null
            || $branch->longitude === null
        ) {
            throw new RuntimeException(
                'Cabang ini belum memiliki koordinat latitude/longitude.'
            );
        }

        $branchStartTime =
            $this->normalizeTime(
                $branch->start_time
            )
            ?? self::DEFAULT_BRANCH_START_TIME;

        /*
        |--------------------------------------------------------------------------
        | 1. SNAPSHOT DRIVER READY
        |--------------------------------------------------------------------------
        |
        | Driver TIDAK lagi dipasangkan dengan vehicle
        | sebelum optimasi.
        |
        | Python memilih vehicle berdasarkan:
        | - box type
        | - volume
        | - cluster
        |
        | Laravel baru memasangkan driver setelah
        | hasil optimizer diterima.
        |
        */

        $usedDriverIds = DeliveryRoute::query()
            ->whereDate(
                'route_date',
                today()
            )
            ->where(
                'status',
                '!=',
                RouteStatus::Cancelled->value
            )
            ->pluck('driver_id');

        $readyDrivers = Driver::query()
            ->where(
                'branch_id',
                $branchId
            )
            ->where(
                'status',
                DriverStatus::Ready
            )
            ->whereNotIn(
                'id',
                $usedDriverIds
            )
            ->orderBy('id')
            ->get();

        if ($readyDrivers->isEmpty()) {
            throw new RuntimeException(
                'Tidak ada driver Ready yang belum '
                . 'memiliki route hari ini pada cabang '
                . 'yang dipilih.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. SNAPSHOT SEMUA VEHICLE ACTIVE
        |--------------------------------------------------------------------------
        |
        | Tidak lagi:
        |
        | min(driver, vehicle)
        | lalu pairing satu-satu.
        |
        | Semua kendaraan yang tersedia dikirim ke Python.
        |
        */

        $usedVehicleIds = DeliveryRoute::query()
            ->whereDate(
                'route_date',
                today()
            )
            ->where(
                'status',
                '!=',
                RouteStatus::Cancelled->value
            )
            ->pluck('vehicle_id');

        $availableVehicles =
            Vehicle::query()
                ->with('vehicleType')
                ->where(
                    'branch_id',
                    $branchId
                )
                ->where(
                    'status',
                    VehicleStatus::Active
                )
                ->whereNotIn(
                    'id',
                    $usedVehicleIds
                )
                ->orderBy('id')
                ->get();

        if ($availableVehicles->isEmpty()) {
            throw new RuntimeException(
                'Tidak ada kendaraan Active yang '
                . 'belum memiliki route hari ini '
                . 'pada cabang yang dipilih.'
            );
        }

        /*
         * Pastikan semua vehicle mempunyai:
         *
         * - vehicle type
         * - box type
         * - volume > 0
         */

        $invalidVehicle =
            $availableVehicles->first(
                function (
                    Vehicle $vehicle
                ): bool {
                    $vehicleType =
                        $vehicle->vehicleType;

                    return ! $vehicleType
                        || ! $this->enumValue(
                            $vehicleType->box_type
                        )
                        || (float) (
                            $vehicleType->volume_m3
                            ?? 0
                        ) <= 0;
                }
            );

        if ($invalidVehicle) {
            throw new RuntimeException(
                "Vehicle {$invalidVehicle->plate_number} "
                . 'belum memiliki box type atau volume '
                . 'kapasitas yang valid. Lengkapi dimensi '
                . 'dan tipe box kendaraan terlebih dahulu.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. SNAPSHOT SALES ORDER HARI INI
        |--------------------------------------------------------------------------
        |
        | orders
        |     = Header Sales Order
        |
        | packages
        |     = Detail produk Sales Order
        |
        | scheduled_date dari ORDERS menjadi sumber utama.
        |
        */

        $packages = Package::query()
            ->with([
                'product',
                'order.store.area',
            ])
            ->where(
                'status',
                PackageStatus::Pending->value
            )
            ->whereHas(
                'order',
                function ($query): void {
                    $query
                        ->whereDate(
                            'scheduled_date',
                            today()
                        )
                        ->where(
                            'status',
                            OrderStatus::Pending->value
                        );
                }
            )
            ->whereHas(
                'order.store.area',
                function ($query) use ($branchId): void {
                    $query->where(
                        'branch_id',
                        $branchId
                    );
                }
            )
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();

        if ($packages->isEmpty()) {

            $pendingOrdersToday = Order::query()
                ->whereDate(
                    'scheduled_date',
                    today()
                )
                ->where(
                    'status',
                    OrderStatus::Pending->value
                )
                ->count();

            $pendingPackagesToday = Package::query()
                ->where(
                    'status',
                    PackageStatus::Pending->value
                )
                ->whereHas(
                    'order',
                    fn($query) =>
                    $query->whereDate(
                        'scheduled_date',
                        today()
                    )
                )
                ->count();

            $branchPendingPackages = Package::query()
                ->where(
                    'status',
                    PackageStatus::Pending->value
                )
                ->whereHas(
                    'order.store.area',
                    fn ($query) =>
                    $query->where(
                        'branch_id',
                        $branchId
                    )
                )
                ->count();

            throw new RuntimeException(
                'Tidak ada Sales Order yang eligible untuk routing. '
                . "Pending Order hari ini: {$pendingOrdersToday}. "
                . "Pending Package hari ini: {$pendingPackagesToday}. "
                . "Pending Package cabang ini: {$branchPendingPackages}. "
                . 'Pastikan tanggal kirim, status order, status item, '
                . 'dan cabang toko sudah sesuai.'
            );
        }

        /*
         * Sales Order baru wajib memiliki:
         *
         * product_id
         * box_type
         * volume_m3
         */

        $invalidPackage =
            $packages->first(
                function (
                    Package $package
                ): bool {
                    return ! $package->product_id
                        || ! $this->enumValue(
                            $package->box_type
                        )
                        || (float) (
                            $package->volume_m3
                            ?? 0
                        ) <= 0;
                }
            );

        if ($invalidPackage) {
            throw new RuntimeException(
                "Package {$invalidPackage->tracking_number} "
                . 'belum memiliki product, box type, '
                . 'atau volume yang valid. '
                . 'Periksa kembali detail Sales Order.'
            );
        }

        /*
         * Validasi koordinat tujuan.
         *
         * Prioritas:
         *
         * Order snapshot
         * ↓
         * Store master
         */

        $invalidDestination =
            $packages->first(
                function (
                    Package $package
                ): bool {
                    $order =
                        $package->order;

                    $store =
                        $order?->store;

                    if (
                        ! $order
                        || ! $store
                    ) {
                        return true;
                    }

                    $latitude =
                        $order->delivery_latitude
                        ?? $store->latitude;

                    $longitude =
                        $order->delivery_longitude
                        ?? $store->longitude;

                    return $latitude === null
                        || $longitude === null;
                }
            );

        if ($invalidDestination) {
            $storeName =
                $invalidDestination
                    ->order
                    ?->store
                    ?->name
                ?? '(store tidak ditemukan)';

            throw new RuntimeException(
                "Tujuan {$storeName} belum memiliki "
                . 'koordinat pengiriman yang valid.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BENTUK DELIVERY DEMAND
        |--------------------------------------------------------------------------
        |
        | Dasar demand:
        |
        | STORE + BOX TYPE
        |
        | Contoh:
        |
        | TOKO A
        | ├── Mayasi Dry
        | └── Kenji Dry
        |
        | menjadi:
        |
        | TOKO A::dry
        |
        |
        | Kalau:
        |
        | TOKO A
        | ├── Mayasi Dry
        | └── Frozen Food Cold
        |
        | menjadi:
        |
        | TOKO A::dry
        | TOKO A::cold_storage
        |
        */

        $packagesByDemandId =
            $packages->groupBy(
                fn (
                    Package $package
                ): string =>
                    $this->demandIdForPackage(
                        $package
                    )
            );

        $demandsPayload =
            $packagesByDemandId
                ->map(
                    function (
                        Collection $demandPackages,
                        string $demandId
                    ): array {
                        /** @var Package $firstPackage */
                        $firstPackage =
                            $demandPackages->first();

                        $order =
                            $firstPackage->order;

                        $store =
                            $order->store;

                        $boxType =
                            $this->enumValue(
                                $firstPackage->box_type
                            );

                        $latitude =
                            $order->delivery_latitude
                            ?? $store->latitude;

                        $longitude =
                            $order->delivery_longitude
                            ?? $store->longitude;

                        $address =
                            $order->delivery_address
                            ?: $store->address;

                        return [

                            /*
                             * ID temporary demand.
                             *
                             * Tidak membutuhkan table baru.
                             */
                            'id' => $demandId,

                            /*
                             * Store.
                             */
                            'store_id' =>
                                $store->uuid,

                            'store_name' =>
                                $store->name,

                            'address' =>
                                $address,

                            'latitude' =>
                                (float) $latitude,

                            'longitude' =>
                                (float) $longitude,

                            /*
                             * Constraint utama jenis box.
                             */
                            'box_type' =>
                                $boxType,

                            /*
                             * Berat hanya informasi.
                             *
                             * TIDAK menjadi constraint vehicle.
                             */
                            'total_weight_kg' =>
                                round(
                                    (float)
                                    $demandPackages
                                        ->sum(
                                            fn (
                                                Package $package
                                            ): float =>
                                                (float)
                                                $package
                                                    ->weight_kg
                                        ),
                                    4
                                ),

                            /*
                             * Volume adalah constraint vehicle.
                             */
                            'total_volume_m3' =>
                                round(
                                    (float)
                                    $demandPackages
                                        ->sum(
                                            fn (
                                                Package $package
                                            ): float =>
                                                (float)
                                                $package
                                                    ->volume_m3
                                        ),
                                    6
                                ),

                            /*
                             * Time Window.
                             */
                            'opening_time' =>
                                $this->normalizeTime(
                                    $store->opening_time
                                ),

                            'closing_time' =>
                                $this->normalizeTime(
                                    $store->closing_time
                                ),

                            'service_duration_minutes' =>
                                (int) (
                                    $store
                                        ->service_duration_minutes
                                    ?? 15
                                ),

                            /*
                             * Package yang merupakan bagian
                             * demand ini.
                             */
                            'package_ids' =>
                                $demandPackages
                                    ->pluck('uuid')
                                    ->values()
                                    ->all(),

                            /*
                             * Sales Order terkait.
                             */
                            'order_ids' =>
                                $demandPackages
                                    ->map(
                                        fn (
                                            Package $package
                                        ) =>
                                            $package
                                                ->order
                                                ?->uuid
                                    )
                                    ->filter()
                                    ->unique()
                                    ->values()
                                    ->all(),

                            /*
                             * Detail produk.
                             *
                             * Ini nantinya juga berguna untuk:
                             * - preview cluster
                             * - Flutter
                             * - manifest driver
                             */
                            'items' =>
                                $demandPackages
                                    ->map(
                                        function (
                                            Package $package
                                        ): array {
                                            return [
                                                'package_id' =>
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
                                                    $package
                                                        ->item,

                                                'quantity' =>
                                                    (int)
                                                    $package
                                                        ->quantity,

                                                'uom' =>
                                                    $this
                                                        ->enumValue(
                                                            $package
                                                                ->uom
                                                        ),

                                                'weight_kg' =>
                                                    (float)
                                                    $package
                                                        ->weight_kg,

                                                'volume_m3' =>
                                                    (float)
                                                    $package
                                                        ->volume_m3,

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
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | 5. VEHICLE PAYLOAD
        |--------------------------------------------------------------------------
        |
        | Tidak ada capacity_weight_kg lagi.
        |
        | Vehicle ditentukan berdasarkan:
        |
        | box_type
        | +
        | volume_m3
        |
        */

        $vehiclesPayload =
            $availableVehicles
                ->map(
                    function (
                        Vehicle $vehicle
                    ): array {
                        return [
                            'id' =>
                                $vehicle->uuid,

                            'plate_number' =>
                                $vehicle->plate_number,

                            'category' =>
                                $this->enumValue(
                                    $vehicle
                                        ->vehicleType
                                        ->category
                                ),

                            'box_type' =>
                                $this->enumValue(
                                    $vehicle
                                        ->vehicleType
                                        ->box_type
                                ),

                            'capacity_volume_m3' =>
                                (float)
                                $vehicle
                                    ->vehicleType
                                    ->volume_m3,
                        ];
                    }
                )
                ->values();

        /*
         * Validasi awal sebelum request ke Python.
         */
        $this->validateDemandVehicleCompatibility(
            $demandsPayload,
            $vehiclesPayload,
            $readyDrivers->count()
        );

        /*
        |--------------------------------------------------------------------------
        | 6. CALL PYTHON
        |--------------------------------------------------------------------------
        |
        | Dilakukan DI LUAR transaction.
        |
        | Contract baru:
        |
        | branch
        | demands
        | vehicles
        | max_routes
        |
        */

        $mlUrl =
            config('services.ml.url');

        if (
            ! is_string($mlUrl)
            || trim($mlUrl) === ''
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
                    . '/cluster-and-route',
                    [
                        'branch' => [
                            'latitude' =>
                                (float)
                                $branch->latitude,

                            'longitude' =>
                                (float)
                                $branch->longitude,

                            'start_time' =>
                                $branchStartTime,
                        ],

                        /*
                         * BUKAN stores lagi.
                         */
                        'demands' =>
                            $demandsPayload->all(),

                        /*
                         * Semua kandidat vehicle.
                         */
                        'vehicles' =>
                            $vehiclesPayload->all(),

                        /*
                         * Maksimum route karena setiap
                         * route membutuhkan satu driver.
                         */
                        'max_routes' =>
                            $readyDrivers->count(),
                    ]
                );

        if ($response->failed()) {
            throw new RuntimeException(
                'ML service gagal: '
                . (
                    $response->json(
                        'detail'
                    )
                    ?? $response->body()
                )
            );
        }

        $clusters =
            $response->json(
                'clusters'
            );

        if (
            ! is_array($clusters)
            || empty($clusters)
        ) {
            throw new RuntimeException(
                'ML service tidak mengembalikan hasil cluster.'
            );
        }

        /*
         * Jumlah cluster tidak boleh melebihi driver.
         */
        if (
            count($clusters)
            > $readyDrivers->count()
        ) {
            throw new RuntimeException(
                'Optimizer menghasilkan route lebih '
                . 'banyak daripada jumlah driver Ready.'
            );
        }

        /*
         * Ambil vehicle pilihan Python.
         */
        $selectedVehicleUuids =
            collect($clusters)
                ->map(
                    fn (
                        array $cluster
                    ) =>
                        $cluster[
                            'vehicle_id'
                        ]
                        ?? null
                )
                ->filter()
                ->values();

        if (
            $selectedVehicleUuids->count()
            !== count($clusters)
        ) {
            throw new RuntimeException(
                'ML service tidak mengembalikan '
                . 'vehicle_id pada seluruh cluster.'
            );
        }

        /*
         * Satu vehicle hanya boleh digunakan
         * satu route.
         */
        if (
            $selectedVehicleUuids
                ->unique()
                ->count()
            !==
            $selectedVehicleUuids
                ->count()
        ) {
            throw new RuntimeException(
                'ML service menggunakan kendaraan '
                . 'yang sama pada lebih dari satu cluster.'
            );
        }

        $availableVehicleByUuid =
            $availableVehicles
                ->keyBy('uuid');

        $unknownVehicleUuid =
            $selectedVehicleUuids
                ->first(
                    fn (
                        string $uuid
                    ): bool =>
                        ! $availableVehicleByUuid
                            ->has(
                                $uuid
                            )
                );

        if ($unknownVehicleUuid) {
            throw new RuntimeException(
                "ML service memilih vehicle "
                . "{$unknownVehicleUuid} yang tidak "
                . 'ada pada snapshot kendaraan tersedia.'
            );
        }

        $selectedVehicleIds =
            $selectedVehicleUuids
                ->map(
                    fn (
                        string $uuid
                    ): int =>
                        (int)
                        $availableVehicleByUuid[
                            $uuid
                        ]
                        ->id
                )
                ->values()
                ->all();

        /*
         * Snapshot package ID.
         */
        $packageIdsSnapshot =
            $packages
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

        /*
         * Snapshot driver.
         */
        $driverIdsSnapshot =
            $readyDrivers
                ->pluck('id')
                ->values()
                ->all();

        /*
        |--------------------------------------------------------------------------
        | 7. PERSISTENCE TRANSACTION
        |--------------------------------------------------------------------------
        |
        | Lock:
        |
        | Driver
        | Vehicle pilihan optimizer
        | Package
        | Order
        |
        */

        $persisted =
            DB::transaction(
                function () use (
                    $branchId,
                    $branchStartTime,
                    $clusters,
                    $packageIdsSnapshot,
                    $driverIdsSnapshot,
                    $selectedVehicleIds
                ): array {

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK DRIVER
                    |--------------------------------------------------------------------------
                    */

                    $lockedDrivers =
                        Driver::query()
                            ->whereIn(
                                'id',
                                $driverIdsSnapshot
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                    /*
                     * Driver mungkin dipakai proses lain
                     * ketika Python sedang bekerja.
                     */
                    $busyDriverIds =
                        DeliveryRoute::query()
                            ->whereDate(
                                'route_date',
                                today()
                            )
                            ->where(
                                'status',
                                '!=',
                                RouteStatus
                                    ::Cancelled
                                    ->value
                            )
                            ->whereIn(
                                'driver_id',
                                $driverIdsSnapshot
                            )
                            ->pluck(
                                'driver_id'
                            )
                            ->all();

                    $availableLockedDrivers =
                        $lockedDrivers
                            ->filter(
                                function (
                                    Driver $driver
                                ) use (
                                    $branchId,
                                    $busyDriverIds
                                ): bool {
                                    return
                                        (int)
                                        $driver
                                            ->branch_id
                                        ===
                                        $branchId

                                        &&

                                        $driver
                                            ->status
                                        ===
                                        DriverStatus
                                            ::Ready

                                        &&

                                        ! in_array(
                                            $driver->id,
                                            $busyDriverIds,
                                            true
                                        );
                                }
                            )
                            ->values();

                    if (
                        $availableLockedDrivers
                            ->count()
                        <
                        count($clusters)
                    ) {
                        throw new RuntimeException(
                            'Jumlah driver Ready berubah '
                            . 'saat optimasi berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK VEHICLE
                    |--------------------------------------------------------------------------
                    */

                    $lockedVehicles =
                        Vehicle::query()
                            ->with(
                                'vehicleType'
                            )
                            ->whereIn(
                                'id',
                                $selectedVehicleIds
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                    if (
                        $lockedVehicles
                            ->count()
                        !==
                        count(
                            $selectedVehicleIds
                        )
                    ) {
                        throw new RuntimeException(
                            'Snapshot kendaraan berubah '
                            . 'saat optimasi berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    $invalidLockedVehicle =
                        $lockedVehicles
                            ->first(
                                fn (
                                    Vehicle $vehicle
                                ): bool =>

                                    (int)
                                    $vehicle
                                        ->branch_id
                                    !==
                                    $branchId

                                    ||

                                    $vehicle
                                        ->status
                                    !==
                                    VehicleStatus
                                        ::Active

                                    ||

                                    ! $vehicle
                                        ->vehicleType

                                    ||

                                    (float) (
                                        $vehicle
                                            ->vehicleType
                                            ->volume_m3
                                        ?? 0
                                    )
                                    <= 0

                                    ||

                                    ! $this
                                        ->enumValue(
                                            $vehicle
                                                ->vehicleType
                                                ->box_type
                                        )
                            );

                    if (
                        $invalidLockedVehicle
                    ) {
                        throw new RuntimeException(
                            'Status, cabang, box type, '
                            . 'atau kapasitas kendaraan '
                            . 'berubah saat optimasi '
                            . 'berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                     * Pastikan vehicle belum digunakan
                     * proses lain.
                     */
                    $vehicleConflict =
                        DeliveryRoute::query()
                            ->whereDate(
                                'route_date',
                                today()
                            )
                            ->where(
                                'status',
                                '!=',
                                RouteStatus
                                    ::Cancelled
                                    ->value
                            )
                            ->whereIn(
                                'vehicle_id',
                                $selectedVehicleIds
                            )
                            ->exists();

                    if (
                        $vehicleConflict
                    ) {
                        throw new RuntimeException(
                            'Kendaraan yang dipilih '
                            . 'optimizer baru saja digunakan '
                            . 'oleh proses lain. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK PACKAGE
                    |--------------------------------------------------------------------------
                    */

                    $lockedPackages =
                        Package::query()
                            ->with([
                                'product',
                                'order.store.area',
                            ])
                            ->whereIn(
                                'id',
                                $packageIdsSnapshot
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                    $lockedPackageIds =
                        $lockedPackages
                            ->pluck('id')
                            ->sort()
                            ->values()
                            ->all();

                    if (
                        $lockedPackageIds
                        !==
                        $packageIdsSnapshot
                    ) {
                        throw new RuntimeException(
                            'Snapshot package berubah '
                            . 'saat optimasi berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK SALES ORDER
                    |--------------------------------------------------------------------------
                    */

                    $orderIds =
                        $lockedPackages
                            ->pluck(
                                'order_id'
                            )
                            ->unique()
                            ->sort()
                            ->values()
                            ->all();

                    $lockedOrders =
                        Order::query()
                            ->whereIn(
                                'id',
                                $orderIds
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('id');

                    if (
                        $lockedOrders
                            ->count()
                        !==
                        count($orderIds)
                    ) {
                        throw new RuntimeException(
                            'Snapshot Sales Order berubah '
                            . 'saat optimasi berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                     * Validasi ulang.
                     */
                    $stalePackage =
                        $lockedPackages
                            ->first(
                                function (
                                    Package $package
                                ) use (
                                    $branchId,
                                    $lockedOrders
                                ): bool {

                                    /** @var Order|null $order */
                                    $order =
                                        $lockedOrders
                                            ->get(
                                                $package
                                                    ->order_id
                                            );

                                    return
                                        ! $order

                                        ||

                                        $package
                                            ->status
                                        !==
                                        PackageStatus
                                            ::Pending

                                        ||

                                        $order
                                            ->status
                                        !==
                                        OrderStatus
                                            ::Pending

                                        ||

                                        ! $order
                                            ->scheduled_date
                                            ?->isToday()

                                        ||

                                        (int)
                                        $package
                                            ->order
                                            ?->store
                                            ?->area
                                            ?->branch_id
                                        !==
                                        $branchId

                                        ||

                                        ! $package
                                            ->product_id

                                        ||

                                        ! $this
                                            ->enumValue(
                                                $package
                                                    ->box_type
                                            )

                                        ||

                                        (float) (
                                            $package
                                                ->volume_m3
                                            ?? 0
                                        )
                                        <= 0;
                                }
                            );

                    if ($stalePackage) {
                        throw new RuntimeException(
                            "Package "
                            . "{$stalePackage->tracking_number} "
                            . 'atau Sales Order terkait '
                            . 'berubah saat optimasi '
                            . 'berlangsung. '
                            . 'Silakan generate ulang.'
                        );
                    }

                    /*
                     * Bentuk ulang demand dari snapshot
                     * yang sudah di-lock.
                     */
                    $packagesByDemandId =
                        $lockedPackages
                            ->groupBy(
                                fn (
                                    Package $package
                                ): string =>
                                    $this
                                        ->demandIdForPackage(
                                            $package
                                        )
                            );

                    $vehiclesByUuid =
                        $lockedVehicles
                            ->keyBy('uuid');

                    /*
                     * Supaya driver assignment
                     * deterministic.
                     */
                    $sortedClusters =
                        collect($clusters)
                            ->sortBy(
                                fn (
                                    array $cluster
                                ) =>
                                    (int) (
                                        $cluster[
                                            'cluster_id'
                                        ]
                                        ?? PHP_INT_MAX
                                    )
                            )
                            ->values();

                    $assignedPackageIds = [];
                    $assignedOrderIds = [];
                    $routeUuids = [];

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE ROUTE
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $sortedClusters
                        as
                        $clusterIndex =>
                        $cluster
                    ) {

                        /*
                         * Driver dipilih Laravel.
                         */
                        $driver =
                            $availableLockedDrivers[
                                $clusterIndex
                            ];

                        /*
                         * Vehicle dipilih Python.
                         */
                        $vehicleUuid =
                            $cluster[
                                'vehicle_id'
                            ]
                            ?? null;

                        $vehicle =
                            $vehiclesByUuid
                                ->get(
                                    $vehicleUuid
                                );

                        if (! $vehicle) {
                            throw new RuntimeException(
                                'Vehicle hasil optimizer '
                                . 'tidak ditemukan pada '
                                . 'snapshot persistence.'
                            );
                        }

                        /*
                         * Validasi box type.
                         */
                        $clusterBoxType =
                            (string) (
                                $cluster[
                                    'box_type'
                                ]
                                ?? ''
                            );

                        $vehicleBoxType =
                            $this
                                ->enumValue(
                                    $vehicle
                                        ->vehicleType
                                        ->box_type
                                );

                        if (
                            $clusterBoxType === ''
                            ||
                            $clusterBoxType
                            !==
                            $vehicleBoxType
                        ) {
                            throw new RuntimeException(
                                "Cluster {$clusterIndex} "
                                . 'memiliki box type yang '
                                . 'tidak sesuai dengan '
                                . "vehicle "
                                . "{$vehicle->plate_number}."
                            );
                        }

                        $optimizedRoute =
                            $cluster[
                                'optimized_route'
                            ]
                            ?? [];

                        if (
                            ! is_array(
                                $optimizedRoute
                            )
                            ||
                            empty(
                                $optimizedRoute
                            )
                        ) {
                            throw new RuntimeException(
                                'ML service mengembalikan '
                                . 'cluster tanpa '
                                . 'optimized_route.'
                            );
                        }

                        /*
                         * Contract stop Python.
                         */
                        foreach (
                            $optimizedRoute
                            as
                            $stopData
                        ) {
                            foreach (
                                [
                                    'demand_id',
                                    'store_id',
                                    'sequence',
                                    'arrival_time',
                                    'service_start',
                                    'service_end',
                                ]
                                as
                                $requiredKey
                            ) {
                                if (
                                    ! array_key_exists(
                                        $requiredKey,
                                        $stopData
                                    )
                                ) {
                                    throw new RuntimeException(
                                        "ML service tidak "
                                        . "mengembalikan field "
                                        . "stop '{$requiredKey}' "
                                        . 'secara lengkap.'
                                    );
                                }
                            }
                        }

                        /*
                         * Demand tidak boleh muncul dua kali
                         * dalam satu route.
                         */
                        $routeDemandIds =
                            collect(
                                $optimizedRoute
                            )
                            ->pluck(
                                'demand_id'
                            )
                            ->values();

                        if (
                            $routeDemandIds
                                ->unique()
                                ->count()
                            !==
                            $routeDemandIds
                                ->count()
                        ) {
                            throw new RuntimeException(
                                'Optimizer mengembalikan '
                                . 'demand yang sama lebih '
                                . 'dari satu kali dalam '
                                . 'satu route.'
                            );
                        }

                        /*
                         * Ambil package seluruh demand
                         * dalam cluster.
                         */
                        $routePackages =
                            $routeDemandIds
                                ->flatMap(
                                    fn (
                                        string $demandId
                                    ): Collection =>
                                        $packagesByDemandId
                                            ->get(
                                                $demandId,
                                                collect()
                                            )
                                );

                        if (
                            $routePackages
                                ->isEmpty()
                        ) {
                            throw new RuntimeException(
                                'Cluster tidak memiliki '
                                . 'package yang dapat '
                                . 'di-assign.'
                            );
                        }

                        /*
                         * Pastikan tidak ada Dry + Cold
                         * dalam satu cluster.
                         */
                        $invalidDemandBoxType =
                            $routePackages
                                ->first(
                                    fn (
                                        Package $package
                                    ): bool =>
                                        $this
                                            ->enumValue(
                                                $package
                                                    ->box_type
                                            )
                                        !==
                                        $clusterBoxType
                                );

                        if (
                            $invalidDemandBoxType
                        ) {
                            throw new RuntimeException(
                                'Optimizer mencampurkan '
                                . 'package dengan box type '
                                . 'berbeda dalam satu '
                                . 'cluster.'
                            );
                        }

                        /*
                         * Validasi kapasitas volume
                         * secara independen di Laravel.
                         */
                        $routeVolume =
                            (float)
                            $routePackages
                                ->sum(
                                    fn (
                                        Package $package
                                    ): float =>
                                        (float)
                                        $package
                                            ->volume_m3
                                );

                        $vehicleCapacity =
                            (float)
                            $vehicle
                                ->vehicleType
                                ->volume_m3;

                        if (
                            $routeVolume
                            >
                            $vehicleCapacity
                            + 0.000001
                        ) {
                            throw new RuntimeException(
                                "Total volume cluster "
                                . "{$routeVolume} m3 "
                                . 'melebihi kapasitas '
                                . "vehicle "
                                . "{$vehicle->plate_number} "
                                . "({$vehicleCapacity} m3)."
                            );
                        }

                        /*
                         * Predicted duration.
                         */
                        $lastStop =
                            end(
                                $optimizedRoute
                            );

                        $predictedDuration =
                            $this
                                ->minutesBetween(
                                    $branchStartTime,
                                    $lastStop[
                                        'service_end'
                                    ]
                                );

                        /*
                         * CREATE DELIVERY ROUTE.
                         */
                        $route =
                            DeliveryRoute::create([
                                'branch_id' =>
                                    $branchId,

                                'driver_id' =>
                                    $driver->id,

                                'vehicle_id' =>
                                    $vehicle->id,

                                /*
                                 * Area tidak digunakan
                                 * sebagai dasar clustering.
                                 */
                                'area_id' =>
                                    null,

                                'route_date' =>
                                    today(),

                                'status' =>
                                    RouteStatus
                                        ::Planned,

                                'predicted_duration_minutes' =>
                                    max(
                                        0,
                                        $predictedDuration
                                    ),

                                'predicted_package_count' =>
                                    $routePackages
                                        ->count(),
                            ]);

                        $routeUuids[] =
                            $route->uuid;

                        /*
                        |--------------------------------------------------------------------------
                        | CREATE DELIVERY STOP
                        |--------------------------------------------------------------------------
                        */

                        foreach (
                            $optimizedRoute
                            as
                            $stopData
                        ) {
                            $demandId =
                                (string)
                                $stopData[
                                    'demand_id'
                                ];

                            $demandPackages =
                                $packagesByDemandId
                                    ->get(
                                        $demandId,
                                        collect()
                                    );

                            if (
                                $demandPackages
                                    ->isEmpty()
                            ) {
                                throw new RuntimeException(
                                    "Demand hasil optimizer "
                                    . "({$demandId}) tidak "
                                    . 'ditemukan pada '
                                    . 'snapshot Laravel.'
                                );
                            }

                            /** @var Package $firstDemandPackage */
                            $firstDemandPackage =
                                $demandPackages
                                    ->first();

                            $store =
                                $firstDemandPackage
                                    ->order
                                    ?->store;

                            /*
                             * Python harus mengembalikan
                             * store yang sama.
                             */
                            if (
                                ! $store
                                ||
                                $store->uuid
                                !==
                                (
                                    $stopData[
                                        'store_id'
                                    ]
                                    ?? null
                                )
                            ) {
                                throw new RuntimeException(
                                    "Store hasil optimizer "
                                    . "untuk demand "
                                    . "{$demandId} tidak "
                                    . 'sesuai dengan '
                                    . 'snapshot Laravel.'
                                );
                            }

                            if (
                                $this
                                    ->enumValue(
                                        $firstDemandPackage
                                            ->box_type
                                    )
                                !==
                                $clusterBoxType
                            ) {
                                throw new RuntimeException(
                                    "Demand {$demandId} "
                                    . 'memiliki box type '
                                    . 'yang tidak sesuai '
                                    . 'dengan cluster.'
                                );
                            }

                            $stop =
                                DeliveryStop::create([
                                    'delivery_route_id' =>
                                        $route->id,

                                    'store_id' =>
                                        $store->id,

                                    'sequence_order' =>
                                        (int)
                                        $stopData[
                                            'sequence'
                                        ],

                                    'predicted_arrival_time' =>
                                        today()
                                            ->setTimeFromTimeString(
                                                $stopData[
                                                    'arrival_time'
                                                ]
                                            ),

                                    'predicted_service_start_time' =>
                                        today()
                                            ->setTimeFromTimeString(
                                                $stopData[
                                                    'service_start'
                                                ]
                                            ),

                                    'predicted_service_end_time' =>
                                        today()
                                            ->setTimeFromTimeString(
                                                $stopData[
                                                    'service_end'
                                                ]
                                            ),

                                    'predicted_waiting_minutes' =>
                                        (int) (
                                            $stopData[
                                                'waiting_minutes'
                                            ]
                                            ?? 0
                                        ),
                                ]);

                            /*
                             * Attach package yang memang
                             * termasuk demand tersebut.
                             *
                             * Ini penting karena satu toko
                             * bisa punya Dry dan Cold.
                             */
                            $pivotRows = [];

                            foreach (
                                $demandPackages
                                as
                                $package
                            ) {
                                if (
                                    in_array(
                                        $package->id,
                                        $assignedPackageIds,
                                        true
                                    )
                                ) {
                                    throw new RuntimeException(
                                        "Package "
                                        . "{$package->tracking_number} "
                                        . 'terdeteksi di-assign '
                                        . 'lebih dari satu kali.'
                                    );
                                }

                                $pivotRows[
                                    $package->id
                                ] = [
                                    'uuid' =>
                                        (string)
                                        Str::uuid(),

                                    'created_at' =>
                                        now(),

                                    'updated_at' =>
                                        now(),
                                ];

                                $assignedPackageIds[] =
                                    $package->id;

                                $assignedOrderIds[] =
                                    $package->order_id;
                            }

                            $stop
                                ->packages()
                                ->attach(
                                    $pivotRows
                                );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | FINAL VALIDATION
                    |--------------------------------------------------------------------------
                    |
                    | Semua package snapshot WAJIB ter-assign.
                    |
                    | Tidak ada partial route generation.
                    |
                    */

                    sort(
                        $assignedPackageIds
                    );

                    if (
                        $assignedPackageIds
                        !==
                        $packageIdsSnapshot
                    ) {
                        throw new RuntimeException(
                            'Tidak seluruh package '
                            . 'Sales Order ter-assign '
                            . 'ke route. Seluruh '
                            . 'perubahan dibatalkan.'
                        );
                    }

                    /*
                     * Package:
                     *
                     * pending → assigned
                     */
                    Package::query()
                        ->whereIn(
                            'id',
                            $assignedPackageIds
                        )
                        ->update([
                            'status' =>
                                PackageStatus
                                    ::Assigned
                                    ->value,
                        ]);

                    /*
                     * Sales Order:
                     *
                     * pending → processing
                     */
                    Order::query()
                        ->whereIn(
                            'id',
                            array_values(
                                array_unique(
                                    $assignedOrderIds
                                )
                            )
                        )
                        ->where(
                            'status',
                            OrderStatus::Pending
                        )
                        ->update([
                            'status' =>
                                OrderStatus
                                    ::Processing
                                    ->value,
                        ]);

                    return [
                        'route_count' =>
                            count(
                                $routeUuids
                            ),

                        'route_uuids' =>
                            $routeUuids,

                        'package_count' =>
                            count(
                                $assignedPackageIds
                            ),
                    ];
                }
            );

        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */

        return [
            'branch_id' =>
                $branchId,

            'demand_count' =>
                $demandsPayload->count(),

            /*
             * Driver yang benar-benar digunakan.
             */
            'driver_count' =>
                $persisted[
                    'route_count'
                ],

            /*
             * Vehicle yang benar-benar digunakan.
             */
            'vehicle_count' =>
                $persisted[
                    'route_count'
                ],

            /*
             * Vehicle kandidat sebelum optimasi.
             */
            'candidate_vehicle_count' =>
                $availableVehicles->count(),

            'package_count' =>
                $persisted[
                    'package_count'
                ],

            'route_count' =>
                $persisted[
                    'route_count'
                ],

            'route_uuids' =>
                $persisted[
                    'route_uuids'
                ],

            'clustering_summary' =>
                $response->json(
                    'clustering_summary'
                ),

            'clusters' =>
                $clusters,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PRE-VALIDATION DEMAND VS VEHICLE
    |--------------------------------------------------------------------------
    |
    | Tujuannya agar error yang sederhana sudah diketahui
    | di Laravel sebelum memanggil Google/Python.
    |
    */

    protected function validateDemandVehicleCompatibility(
        Collection $demands,
        Collection $vehicles,
        int $readyDriverCount
    ): void {
        $demandsByBoxType =
            $demands->groupBy(
                'box_type'
            );

        $vehiclesByBoxType =
            $vehicles->groupBy(
                'box_type'
            );

        /*
         * Setiap box type minimal membutuhkan
         * satu route.
         */
        if (
            $demandsByBoxType
                ->count()
            >
            $readyDriverCount
        ) {
            throw new RuntimeException(
                'Jumlah jenis box pada Sales Order '
                . 'hari ini melebihi jumlah driver Ready. '
                . 'Minimal dibutuhkan satu route/driver '
                . 'untuk setiap jenis box.'
            );
        }

        /*
         * Lower bound jumlah route.
         */
        $minimumRouteLowerBound = 0;

        foreach (
            $demandsByBoxType
            as
            $boxType =>
            $boxDemands
        ) {
            /** @var Collection $candidateVehicles */
            $candidateVehicles =
                $vehiclesByBoxType
                    ->get(
                        $boxType,
                        collect()
                    );

            /*
             * Tidak ada vehicle sesuai box.
             */
            if (
                $candidateVehicles
                    ->isEmpty()
            ) {
                throw new RuntimeException(
                    "Ada Sales Order bertipe "
                    . "{$boxType}, tetapi tidak "
                    . 'ada vehicle Active dengan '
                    . 'box type yang sama.'
                );
            }

            /*
             * Kapasitas vehicle terbesar.
             */
            $maxVehicleCapacity =
                (float)
                $candidateVehicles
                    ->max(
                        'capacity_volume_m3'
                    );

            /*
             * Satu toko sendiri saja tidak muat.
             */
            $oversizedDemand =
                $boxDemands
                    ->first(
                        fn (
                            array $demand
                        ): bool =>
                            (float)
                            $demand[
                                'total_volume_m3'
                            ]
                            >
                            $maxVehicleCapacity
                            + 0.000001
                    );

            if ($oversizedDemand) {
                throw new RuntimeException(
                    "Demand "
                    . "{$oversizedDemand['store_name']} "
                    . "({$boxType}) memiliki volume "
                    . "{$oversizedDemand['total_volume_m3']} "
                    . 'm3, lebih besar dari kapasitas '
                    . "vehicle {$boxType} terbesar "
                    . "({$maxVehicleCapacity} m3)."
                );
            }

            /*
             * Total volume semua demand tipe ini.
             */
            $totalDemandVolume =
                (float)
                $boxDemands
                    ->sum(
                        'total_volume_m3'
                    );

            /*
             * Total kapasitas vehicle tersedia.
             */
            $totalVehicleCapacity =
                (float)
                $candidateVehicles
                    ->sum(
                        'capacity_volume_m3'
                    );

            if (
                $totalDemandVolume
                >
                $totalVehicleCapacity
                + 0.000001
            ) {
                throw new RuntimeException(
                    "Total volume Sales Order "
                    . "{$boxType} "
                    . "({$totalDemandVolume} m3) "
                    . 'melebihi total kapasitas '
                    . "vehicle {$boxType} yang "
                    . 'tersedia '
                    . "({$totalVehicleCapacity} m3)."
                );
            }

            /*
             * Estimasi batas bawah jumlah route.
             *
             * Ini hanya pre-check.
             * Keputusan final tetap Python.
             */
            $minimumRoutesForType =
                (int)
                ceil(
                    $totalDemandVolume
                    /
                    $maxVehicleCapacity
                );

            $minimumRoutesForType =
                max(
                    1,
                    $minimumRoutesForType
                );

            if (
                $minimumRoutesForType
                >
                $candidateVehicles
                    ->count()
            ) {
                throw new RuntimeException(
                    "Jumlah vehicle {$boxType} "
                    . 'tidak cukup untuk volume '
                    . 'Sales Order hari ini.'
                );
            }

            $minimumRouteLowerBound +=
                $minimumRoutesForType;
        }

        if (
            $minimumRouteLowerBound
            >
            $readyDriverCount
        ) {
            throw new RuntimeException(
                "Estimasi minimum membutuhkan "
                . "{$minimumRouteLowerBound} route, "
                . 'sedangkan driver Ready hanya '
                . "{$readyDriverCount}."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY DEMAND ID
    |--------------------------------------------------------------------------
    |
    | Tidak membutuhkan table.
    |
    | Format:
    |
    | STORE_UUID::BOX_TYPE
    |
    | Contoh:
    |
    | f8e4...::dry
    |
    */

    protected function demandIdForPackage(
        Package $package
    ): string {
        $storeUuid =
            $package
                ->order
                ?->store
                ?->uuid;

        $boxType =
            $this->enumValue(
                $package->box_type
            );

        if (
            ! $storeUuid
            || ! $boxType
        ) {
            throw new RuntimeException(
                "Package "
                . "{$package->tracking_number} "
                . 'tidak dapat dibentuk '
                . 'menjadi delivery demand.'
            );
        }

        return
            $storeUuid
            . '::'
            . $boxType;
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
            return (string)
                $value->value;
        }

        if ($value === null) {
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

    /*
    |--------------------------------------------------------------------------
    | TIME HELPERS
    |--------------------------------------------------------------------------
    */

    protected function normalizeTime(
        ?string $value
    ): ?string {
        if (
            $value === null
            ||
            trim($value) === ''
        ) {
            return null;
        }

        if (
            ! preg_match(
                '/^(\d{2}):(\d{2})(?::\d{2})?$/',
                trim($value),
                $matches
            )
        ) {
            throw new RuntimeException(
                "Format waktu tidak valid: {$value}"
            );
        }

        return
            $matches[1]
            . ':'
            . $matches[2];
    }

    protected function minutesBetween(
        string $start,
        string $end
    ): int {
        [
            $startHour,
            $startMinute
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
            $endMinute
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
            + $startMinute;

        $endMinutes =
            $endHour * 60
            + $endMinute;

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
}