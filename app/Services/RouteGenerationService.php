<?php

namespace App\Services;

use App\Enums\DriverStatus;
use App\Enums\RouteStatus;
use App\Enums\VehicleStatus;
use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Driver;
use App\Models\Store;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RouteGenerationService
{
    protected const DEFAULT_BRANCH_START_TIME = '08:00';
    protected const DEFAULT_ROUTE_PREPARATION_MINUTES = 60;

    public function __construct(
        protected DeliverySourceService $deliverySource
    ) {
    }

    /**
     * Route Generation V3
     *
     * Source of truth:
     * - so_sda
     * - cust_sda
     *
     * Tidak lagi memakai Order / Package / Product sebagai sumber optimizer.
     * Tabel stores hanya dipakai sebagai mirror customer agar relasi
     * DeliveryStop existing tetap kompatibel.
     */
    public function generateTodayRoutes(array $data): array
    {
        set_time_limit(300);
        /*
         * Nama method dipertahankan untuk compatibility UI lama.
         * Secara bisnis method ini sekarang dapat membuat route H+2 dari source_date,
         * sehingga route_date tidak harus sama dengan hari saat tombol ditekan.
         */
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

        /*
         * PREP INTEGRASI SISTEM UTAMA - MAPPING CABANG
         * Saat ini Branch.cab_id adalah kode cabang yang dipakai source SO.
         * Jika kode cabang sistem utama berbeda, gunakan mapping resmi di master
         * branch / tabel mapping, bukan if/else yang tersebar di service.
         */
        if (trim((string) ($branch->cab_id ?? '')) === '') {
            throw new RuntimeException(
                'Cabang ini belum memiliki CAB ID sumber data.'
            );
        }

        $sourceDate = $this->resolveSourceDate(
            $data['source_date'] ?? null
        );

        /*
        |------------------------------------------------------------------
        | ATURAN BISNIS TANGGAL ROUTE
        |------------------------------------------------------------------
        |
        | visit_date / source_date = tanggal Sales Order masuk.
        | route_date               = source_date + 2 hari kalender.
        |
        | Contoh:
        | source_date = 2026-09-07
        | route_date  = 2026-09-09
        |
        | PREP INTEGRASI SISTEM UTAMA:
        | Jika aturan H+2 berubah menjadi hari kerja, ubah hanya method
        | calculateRouteDate(). Jangan sebarkan perhitungan tanggal ke query lain.
        |
        */
        $routeDate = $this->calculateRouteDate($sourceDate);

        /*
         * Jika UI mengirim route_date, validasi agar tidak bisa menyimpang
         * dari aturan H+2 yang menjadi source of truth di service ini.
         */
        if (
            isset($data['route_date'])
            && trim((string) $data['route_date']) !== ''
        ) {
            $requestedRouteDate = $this->resolveRouteDate(
                $data['route_date']
            );

            if ($requestedRouteDate !== $routeDate) {
                throw new RuntimeException(
                    "Route date harus H+2 dari tanggal SO masuk. "
                    . "Expected {$routeDate}, received {$requestedRouteDate}."
                );
            }
        }

        /*
         * Karena source SO belum memiliki status assigned di aplikasi ini,
         * jangan generate dua kali untuk branch + route date yang sama.
         */
        $this->assertNoExistingRoutes(
            branchId: $branchId,
            routeDate: $routeDate,
        );

        $branchStartTime =
            $this->normalizeTime(
                $branch->start_time
            )
            ?? self::DEFAULT_BRANCH_START_TIME;

        [
            'generated_at' => $generatedAt,
            'delivery_start_time' => $deliveryStartTime,
        ] = $this->resolvePlanningTime(
            routeDate: $routeDate,
            branchStartTime: $branchStartTime,
        );

        /*
        |--------------------------------------------------------------------------
        | 1. DRIVER READY
        |--------------------------------------------------------------------------
        */

        $usedDriverIds = DeliveryRoute::query()
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->pluck('driver_id');

        $readyDrivers = Driver::query()
            ->where('branch_id', $branchId)
            ->where('status', DriverStatus::Ready)
            ->whereNotIn('id', $usedDriverIds)
            ->orderBy('id')
            ->get();

        if ($readyDrivers->isEmpty()) {
            throw new RuntimeException(
                'Tidak ada driver Ready yang tersedia pada cabang yang dipilih.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. VEHICLE ACTIVE
        |--------------------------------------------------------------------------
        |
        | Tidak lagi membutuhkan box_type / volume_m3.
        | Vehicle adalah resource satu-per-route.
        */

        $usedVehicleIds = DeliveryRoute::query()
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->pluck('vehicle_id');

        $availableVehicles = Vehicle::query()
            ->with('vehicleType')
            ->where('branch_id', $branchId)
            ->where('status', VehicleStatus::Active)
            ->whereNotIn('id', $usedVehicleIds)
            ->orderBy('id')
            ->get();

        if ($availableVehicles->isEmpty()) {
            throw new RuntimeException(
                'Tidak ada kendaraan Active yang tersedia pada cabang yang dipilih.'
            );
        }

        $routeSlots = min(
            $readyDrivers->count(),
            $availableVehicles->count()
        );

        if ($routeSlots <= 0) {
            throw new RuntimeException(
                'Resource driver/vehicle tidak mencukupi untuk membuat route.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. SOURCE SNAPSHOT: so_sda + cust_sda
        |--------------------------------------------------------------------------
        */

        $source = $this->deliverySource->build(
            branch: $branch,
            sourceDate: $sourceDate,
        );

        /** @var Collection<int, array> $demandsPayload */
        $demandsPayload = $source['demands'];

        if ($demandsPayload->isEmpty()) {
            throw new RuntimeException(
                "Tidak ada Sales Order eligible pada {$sourceDate} untuk "
                . "CAB ID {$branch->cab_id}."
            );
        }

        /*
         * Mirror customer ke stores. Tidak ada lagi CRUD manual store sebagai
         * sumber route; ini hanya compatibility layer DeliveryStop.store_id.
         */
        $storeByCustomerId =
            $this->deliverySource->syncStores(
                $demandsPayload
            );

        $missingStore = $demandsPayload
            ->first(
                fn (array $demand): bool =>
                    ! $storeByCustomerId->has(
                        (string) $demand['customer_id']
                    )
            );

        if ($missingStore) {
            throw new RuntimeException(
                'Mirror customer ke tabel stores tidak lengkap.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. VEHICLE PAYLOAD
        |--------------------------------------------------------------------------
        */

        $vehiclesPayload = $availableVehicles
            ->map(
                fn (Vehicle $vehicle): array => [
                    'id' => (string) $vehicle->uuid,
                    'plate_number' => (string) $vehicle->plate_number,
                    'category' => $this->enumValue(
                        $vehicle->vehicleType?->category
                    ),
                ]
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 5. CALL PYTHON
        |--------------------------------------------------------------------------
        */

        $mlUrl = config('services.ml.url');

        if (
            ! is_string($mlUrl)
            || trim($mlUrl) === ''
        ) {
            throw new RuntimeException(
                'Konfigurasi services.ml.url belum diisi.'
            );
        }

        $response = Http::connectTimeout(10)
            ->timeout(240)
            ->post(
                rtrim($mlUrl, '/') . '/cluster-and-route',
                [
                    'branch' => [
                        'latitude' => (float) $branch->latitude,
                        'longitude' => (float) $branch->longitude,
                        'start_time' => $deliveryStartTime,
                    ],
                    'demands' => $demandsPayload->all(),
                    'vehicles' => $vehiclesPayload->all(),
                    'max_routes' => $routeSlots,
                ]
            );

        if ($response->failed()) {
            $detail = $response->json('detail');

            if (is_array($detail)) {
                $detail = collect($detail)
                    ->map(function ($item): string {
                        if (! is_array($item)) {
                            return (string) $item;
                        }

                        $loc = isset($item['loc']) && is_array($item['loc'])
                            ? implode('.', array_map('strval', $item['loc']))
                            : 'request';

                        $msg = (string) ($item['msg'] ?? 'Validation error');

                        return $loc . ': ' . $msg;
                    })
                    ->implode(' | ');
            }

            if (! is_string($detail) || trim($detail) === '') {
                $detail = $response->body();
            }

            throw new RuntimeException(
                'ML service gagal (HTTP ' . $response->status() . '): ' . $detail
            );
        }

        $clusters = $response->json('clusters', []);
        $deferredDemands = $response->json('deferred_demands', []);

        if (! is_array($clusters)) {
            throw new RuntimeException(
                'Format clusters dari ML service tidak valid.'
            );
        }

        if (! empty($deferredDemands)) {
            throw new RuntimeException(
                'Optimizer tidak dapat mengalokasikan seluruh customer. '
                . 'Route generation dibatalkan agar tidak terjadi partial route.'
            );
        }

        if (empty($clusters)) {
            throw new RuntimeException(
                'ML service tidak mengembalikan hasil cluster.'
            );
        }

        if (count($clusters) > $routeSlots) {
            throw new RuntimeException(
                'Optimizer menghasilkan route lebih banyak daripada '
                . 'resource driver/vehicle yang tersedia.'
            );
        }

        /*
         * Vehicle dipilih Python, tetapi tidak berdasarkan kapasitas/box type.
         */
        $selectedVehicleUuids = collect($clusters)
            ->map(
                fn (array $cluster) =>
                    $cluster['vehicle_id'] ?? null
            )
            ->filter()
            ->map(fn ($value): string => (string) $value)
            ->values();

        if (
            $selectedVehicleUuids->count()
            !== count($clusters)
        ) {
            throw new RuntimeException(
                'ML service tidak mengembalikan vehicle_id pada seluruh cluster.'
            );
        }

        if (
            $selectedVehicleUuids->unique()->count()
            !== $selectedVehicleUuids->count()
        ) {
            throw new RuntimeException(
                'ML service menggunakan kendaraan yang sama pada lebih dari satu route.'
            );
        }

        $availableVehicleByUuid = $availableVehicles
            ->keyBy(fn (Vehicle $vehicle): string => (string) $vehicle->uuid);

        $unknownVehicleUuid = $selectedVehicleUuids
            ->first(
                fn (string $uuid): bool =>
                    ! $availableVehicleByUuid->has($uuid)
            );

        if ($unknownVehicleUuid) {
            throw new RuntimeException(
                "ML service memilih vehicle {$unknownVehicleUuid} "
                . 'yang tidak ada pada snapshot kendaraan tersedia.'
            );
        }

        $selectedVehicleIds = $selectedVehicleUuids
            ->map(
                fn (string $uuid): int =>
                    (int) $availableVehicleByUuid[$uuid]->id
            )
            ->values()
            ->all();

        $driverIdsSnapshot = $readyDrivers
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $storeIdsSnapshot = $storeByCustomerId
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $demandById = $demandsPayload
            ->keyBy(fn (array $demand): string => (string) $demand['id']);

        $expectedDemandIds = $demandById
            ->keys()
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values()
            ->all();

        $sourceFingerprint = (string) $source['fingerprint'];

        /*
        |--------------------------------------------------------------------------
        | 6. PERSISTENCE TRANSACTION
        |--------------------------------------------------------------------------
        |
        | Source SO/customer bersifat read-only. Tidak ada update status ke so_sda.
        */

        $persisted = DB::transaction(
            function () use (
                $branch,
                $branchId,
                $sourceDate,
                $routeDate,
                $deliveryStartTime,
                $clusters,
                $driverIdsSnapshot,
                $selectedVehicleIds,
                $storeIdsSnapshot,
                $demandById,
                $expectedDemandIds,
                $sourceFingerprint,
                $source
            ): array {
                /*
                 * Race guard: jangan ada generate paralel untuk branch/date sama.
                 */
                $this->assertNoExistingRoutes(
                    branchId: $branchId,
                    routeDate: $routeDate,
                    lock: true,
                );

                $lockedDrivers = Driver::query()
                    ->whereIn('id', $driverIdsSnapshot)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $busyDriverIds = DeliveryRoute::query()
                    ->whereDate('route_date', $routeDate)
                    ->where('status', '!=', RouteStatus::Cancelled->value)
                    ->whereIn('driver_id', $driverIdsSnapshot)
                    ->pluck('driver_id')
                    ->all();

                $availableLockedDrivers = $lockedDrivers
                    ->filter(
                        fn (Driver $driver): bool =>
                            (int) $driver->branch_id === $branchId
                            && $driver->status === DriverStatus::Ready
                            && ! in_array(
                                $driver->id,
                                $busyDriverIds,
                                true
                            )
                    )
                    ->values();

                if (
                    $availableLockedDrivers->count()
                    < count($clusters)
                ) {
                    throw new RuntimeException(
                        'Jumlah driver Ready berubah saat optimasi berlangsung. '
                        . 'Silakan generate ulang.'
                    );
                }

                $lockedVehicles = Vehicle::query()
                    ->whereIn('id', $selectedVehicleIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if (
                    $lockedVehicles->count()
                    !== count($selectedVehicleIds)
                ) {
                    throw new RuntimeException(
                        'Snapshot kendaraan berubah saat optimasi berlangsung.'
                    );
                }

                $invalidVehicle = $lockedVehicles
                    ->first(
                        fn (Vehicle $vehicle): bool =>
                            (int) $vehicle->branch_id !== $branchId
                            || $vehicle->status !== VehicleStatus::Active
                    );

                if ($invalidVehicle) {
                    throw new RuntimeException(
                        'Status atau cabang kendaraan berubah saat optimasi berlangsung.'
                    );
                }

                $vehicleConflict = DeliveryRoute::query()
                    ->whereDate('route_date', $routeDate)
                    ->where('status', '!=', RouteStatus::Cancelled->value)
                    ->whereIn('vehicle_id', $selectedVehicleIds)
                    ->exists();

                if ($vehicleConflict) {
                    throw new RuntimeException(
                        'Kendaraan hasil optimizer baru saja digunakan proses lain.'
                    );
                }

                $lockedStores = Store::query()
                    ->whereIn('id', $storeIdsSnapshot)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('code');

                if (
                    $lockedStores->count()
                    !== count($storeIdsSnapshot)
                ) {
                    throw new RuntimeException(
                        'Mirror customer berubah saat optimasi berlangsung.'
                    );
                }

                /*
                 * Re-read source setelah proses Python. Jika database source
                 * direplace di tengah proses, jangan persist hasil lama.
                 */
                $freshSource = $this->deliverySource->build(
                    branch: $branch,
                    sourceDate: $sourceDate,
                );

                if (
                    (string) $freshSource['fingerprint']
                    !== $sourceFingerprint
                ) {
                    throw new RuntimeException(
                        'Data so_sda/cust_sda berubah saat optimasi berlangsung. '
                        . 'Silakan generate ulang.'
                    );
                }

                $vehiclesByUuid = $lockedVehicles
                    ->keyBy(fn (Vehicle $vehicle): string => (string) $vehicle->uuid);

                $sortedClusters = collect($clusters)
                    ->sortBy(
                        fn (array $cluster): int =>
                            (int) ($cluster['cluster_id'] ?? PHP_INT_MAX)
                    )
                    ->values();

                $assignedDemandIds = [];
                $routeUuids = [];
                $assignedItemCount = 0;

                foreach (
                    $sortedClusters
                    as $clusterIndex => $cluster
                ) {
                    $driver = $availableLockedDrivers[$clusterIndex];

                    $vehicleUuid = (string) ($cluster['vehicle_id'] ?? '');
                    $vehicle = $vehiclesByUuid->get($vehicleUuid);

                    if (! $vehicle) {
                        throw new RuntimeException(
                            'Vehicle hasil optimizer tidak ditemukan pada snapshot persistence.'
                        );
                    }

                    $optimizedRoute = $cluster['optimized_route'] ?? [];

                    if (
                        ! is_array($optimizedRoute)
                        || empty($optimizedRoute)
                    ) {
                        throw new RuntimeException(
                            'ML service mengembalikan cluster tanpa optimized_route.'
                        );
                    }

                    foreach ($optimizedRoute as $stopData) {
                        foreach (
                            [
                                'demand_id',
                                'sequence',
                                'arrival_time',
                                'service_start',
                                'service_end',
                            ]
                            as $requiredKey
                        ) {
                            if (! array_key_exists($requiredKey, $stopData)) {
                                throw new RuntimeException(
                                    "ML service tidak mengembalikan field stop "
                                    . "'{$requiredKey}' secara lengkap."
                                );
                            }
                        }
                    }

                    $routeDemandIds = collect($optimizedRoute)
                        ->pluck('demand_id')
                        ->map(fn ($id): string => (string) $id)
                        ->values();

                    if (
                        $routeDemandIds->unique()->count()
                        !== $routeDemandIds->count()
                    ) {
                        throw new RuntimeException(
                            'Optimizer mengembalikan demand yang sama lebih dari satu kali.'
                        );
                    }

                    $routeItemCount = $routeDemandIds
                        ->sum(
                            function (string $demandId) use ($demandById): int {
                                $demand = $demandById->get($demandId);

                                if (! $demand) {
                                    throw new RuntimeException(
                                        "Demand optimizer {$demandId} tidak ditemukan "
                                        . 'pada source snapshot.'
                                    );
                                }

                                return count($demand['items'] ?? []);
                            }
                        );

                    $lastStop = collect($optimizedRoute)
                        ->sortBy('sequence')
                        ->last();

                    $predictedDuration = $this->minutesBetween(
                        $deliveryStartTime,
                        (string) $lastStop['service_end']
                    );

                    $route = DeliveryRoute::create([
                        'branch_id' => $branchId,
                        'driver_id' => $driver->id,
                        'vehicle_id' => $vehicle->id,
                        'area_id' => null,
                        'source_date' => $sourceDate,
                        'route_date' => $routeDate,
                        'status' => RouteStatus::Planned,
                        'predicted_duration_minutes' => max(
                            0,
                            $predictedDuration
                        ),

                        /*
                         * Kolom legacy. Untuk sementara berisi jumlah item SO
                         * yang dibawa route sampai schema UI selesai direvisi.
                         */
                        'predicted_package_count' => $routeItemCount,
                    ]);

                    $routeUuids[] = (string) $route->uuid;

                    foreach ($optimizedRoute as $stopData) {
                        $demandId = (string) $stopData['demand_id'];

                        if (
                            in_array(
                                $demandId,
                                $assignedDemandIds,
                                true
                            )
                        ) {
                            throw new RuntimeException(
                                "Demand {$demandId} terdeteksi di-assign lebih dari sekali."
                            );
                        }

                        $demand = $demandById->get($demandId);

                        if (! $demand) {
                            throw new RuntimeException(
                                "Demand {$demandId} tidak ditemukan pada source snapshot."
                            );
                        }

                        $customerId = (string) $demand['customer_id'];
                        $store = $lockedStores->get($customerId);

                        if (! $store) {
                            throw new RuntimeException(
                                "Store mirror customer {$customerId} tidak ditemukan."
                            );
                        }

                        DeliveryStop::create([
                            'delivery_route_id' => $route->id,
                            'store_id' => $store->id,
                            'source_customer_code' => $customerId,
                            'source_so_numbers' => $demand['so_numbers'] ?? [],
                            'sequence_order' => (int) $stopData['sequence'],
                            'predicted_arrival_time' => $this->routeDateTime(
                                $routeDate,
                                (string) $stopData['arrival_time']
                            ),
                            'predicted_service_start_time' => $this->routeDateTime(
                                $routeDate,
                                (string) $stopData['service_start']
                            ),
                            'predicted_service_end_time' => $this->routeDateTime(
                                $routeDate,
                                (string) $stopData['service_end']
                            ),
                            'predicted_waiting_minutes' => (int) (
                                $stopData['waiting_minutes'] ?? 0
                            ),
                        ]);

                        $assignedDemandIds[] = $demandId;
                        $assignedItemCount += count(
                            $demand['items'] ?? []
                        );
                    }
                }

                sort($assignedDemandIds);

                if ($assignedDemandIds !== $expectedDemandIds) {
                    throw new RuntimeException(
                        'Tidak seluruh customer demand ter-assign ke route. '
                        . 'Seluruh perubahan dibatalkan.'
                    );
                }

                return [
                    'route_count' => count($routeUuids),
                    'route_uuids' => $routeUuids,
                    'item_count' => $assignedItemCount,
                    'demand_count' => count($assignedDemandIds),
                ];
            }
        );

        return [
            'branch_id' => $branchId,
            'cab_id' => (string) $branch->cab_id,
            'source_date' => $sourceDate,
            'route_date' => $routeDate,
            'branch_start_time' => $branchStartTime,
            'generated_at' => $generatedAt,
            'preparation_minutes' => self::DEFAULT_ROUTE_PREPARATION_MINUTES,
            'delivery_start_time' => $deliveryStartTime,

            'demand_count' => $persisted['demand_count'],
            'customer_count' => $source['customer_count'],
            'order_count' => $source['order_count'],
            'source_line_count' => $source['row_count'],
            'item_count' => $persisted['item_count'],

            /* compatibility sementara dengan ListDeliveryRoutes lama */
            'package_count' => $source['row_count'],

            'driver_count' => $persisted['route_count'],
            'vehicle_count' => $persisted['route_count'],
            'candidate_vehicle_count' => $availableVehicles->count(),
            'route_count' => $persisted['route_count'],
            'route_uuids' => $persisted['route_uuids'],

            'clustering_summary' => $response->json(
                'clustering_summary'
            ),
            'clusters' => $clusters,
            'deferred_demands' => [],
        ];
    }

    protected function resolveSourceDate(
        mixed $value
    ): string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return today()->toDateString();
        }

        try {
            return Carbon::parse(
                (string) $value
            )->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException(
                'Format source_date tidak valid.'
            );
        }
    }

    protected function calculateRouteDate(
        string $sourceDate
    ): string {
        return Carbon::parse($sourceDate)
            ->startOfDay()
            ->addDays(2)
            ->toDateString();
    }

    protected function resolveRouteDate(
        mixed $value
    ): string {
        try {
            return Carbon::parse(
                (string) $value
            )->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException(
                'Format route_date tidak valid.'
            );
        }
    }

    protected function resolvePlanningTime(
        string $routeDate,
        string $branchStartTime
    ): array {
        $routeDateTime = Carbon::parse($routeDate)
            ->startOfDay();

        $branchStartDateTime = $routeDateTime
            ->copy()
            ->setTimeFromTimeString(
                $branchStartTime
            );

        $now = now();

        /*
         * Jika route_date = hari ini, jangan menghasilkan ETA di masa lalu.
         * Untuk historical/demo date gunakan jam operasional cabang.
         */
        $planningBaseTime =
            $routeDateTime->isSameDay($now)
            && $now->greaterThan($branchStartDateTime)
                ? $now->copy()
                : $branchStartDateTime->copy();

        $deliveryStartDateTime = $planningBaseTime
            ->copy()
            ->addMinutes(
                self::DEFAULT_ROUTE_PREPARATION_MINUTES
            );

        return [
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'delivery_start_time' => $deliveryStartDateTime->format('H:i'),
        ];
    }

    protected function assertNoExistingRoutes(
        int $branchId,
        string $routeDate,
        bool $lock = false
    ): void {
        $query = DeliveryRoute::query()
            ->where('branch_id', $branchId)
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value);

        $exists = $lock
            ? $query->lockForUpdate()->first() !== null
            : $query->exists();

        if ($exists) {
            throw new RuntimeException(
                "Route branch ini untuk {$routeDate} sudah pernah dibuat. "
                . 'Batalkan route existing terlebih dahulu jika memang ingin generate ulang.'
            );
        }
    }

    protected function routeDateTime(
        string $routeDate,
        string $time
    ): Carbon {
        $normalized = $this->normalizeTime($time);

        if (! $normalized) {
            throw new RuntimeException(
                "Waktu optimizer tidak valid: {$time}"
            );
        }

        return Carbon::parse($routeDate)
            ->startOfDay()
            ->setTimeFromTimeString($normalized);
    }

    protected function enumValue(
        mixed $value
    ): ?string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }

    protected function normalizeTime(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $value = trim((string) $value);

        if ($value === '') {
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

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new RuntimeException(
                "Format waktu tidak valid: {$value}"
            );
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    protected function minutesBetween(
        string $start,
        string $end
    ): int {
        [$startHour, $startMinute] = array_map(
            'intval',
            explode(':', $start)
        );

        [$endHour, $endMinute] = array_map(
            'intval',
            explode(':', $end)
        );

        $startMinutes = $startHour * 60 + $startMinute;
        $endMinutes = $endHour * 60 + $endMinute;

        if ($endMinutes < $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }
}
