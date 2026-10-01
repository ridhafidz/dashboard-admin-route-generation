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

    public function preview(
        DeliveryRoute|int $route,
        ?Collection $stops = null
    ): array {
        $route = $this->loadRoute($route);
        $this->assertRouteEditable($route);

        $stops = ($stops ?? $route->deliveryStops)
            ->sortBy('sequence_order')
            ->values();

        foreach ($stops as $stop) {
            if (! $stop instanceof DeliveryStop) {
                throw new RuntimeException('Collection route harus berisi DeliveryStop.');
            }

            $stop->loadMissing('store');
        }

        $startTime = $this->resolveRouteStartTime($route);
        $snapshot = $stops
            ->pluck('uuid')
            ->map(fn ($uuid): string => (string) $uuid)
            ->sort()
            ->values()
            ->all();

        if ($stops->isEmpty()) {
            return [
                'vehicle_id' => (string) $route->vehicle?->uuid,
                'plate_number' => (string) ($route->vehicle?->plate_number ?? ''),
                'stop_count' => 0,
                'total_distance_km' => 0.0,
                'total_travel_minutes' => 0,
                'route_duration_minutes' => 0,
                'return_arrival_time' => $startTime,
                'optimized_route' => [],
                'start_time' => $startTime,
                '_snapshot_stop_uuids' => $snapshot,
            ];
        }

        $payload = [
            'branch' => [
                'latitude' => (float) $route->branch->latitude,
                'longitude' => (float) $route->branch->longitude,
                'start_time' => $startTime,
            ],
            'vehicle' => [
                'id' => (string) $route->vehicle->uuid,
                'plate_number' => (string) $route->vehicle->plate_number,
                'category' => $this->enumValue($route->vehicle?->vehicleType?->category),
            ],
            'demands' => $stops
                ->map(fn (DeliveryStop $stop): array => $this->buildDemandPayload($stop))
                ->values()
                ->all(),
        ];

        $result = $this->callOptimizer($payload);
        $this->validateOptimizerResult($result, $stops);

        return array_merge($result, [
            'start_time' => $startTime,
            '_snapshot_stop_uuids' => $snapshot,
        ]);
    }

    public function reoptimize(DeliveryRoute|int $route): array
    {
        $route = $this->loadRoute($route);
        $preview = $this->preview($route);
        $this->applyPreview($route, $preview);
        unset($preview['_snapshot_stop_uuids']);

        return $preview;
    }

    public function applyPreview(DeliveryRoute|int $route, array $preview): void
    {
        $route = $this->loadRoute($route);
        $this->assertRouteEditable($route);

        foreach (['optimized_route', 'start_time', '_snapshot_stop_uuids'] as $key) {
            if (! array_key_exists($key, $preview)) {
                throw new RuntimeException("Preview re-optimization tidak memiliki {$key}.");
            }
        }

        $this->validateOptimizerResult($preview, $route->deliveryStops);
        $this->persistOptimization($route, $preview);
    }

    protected function loadRoute(DeliveryRoute|int $route): DeliveryRoute
    {
        $routeId = $route instanceof DeliveryRoute ? $route->id : $route;

        $loaded = DeliveryRoute::query()
            ->with([
                'branch',
                'vehicle.vehicleType',
                'deliveryStops' => fn ($query) => $query->orderBy('sequence_order'),
                'deliveryStops.store',
            ])
            ->findOrFail($routeId);

        if (! $loaded->branch || $loaded->branch->latitude === null || $loaded->branch->longitude === null) {
            throw new RuntimeException('Koordinat branch belum lengkap.');
        }

        if (! $loaded->vehicle) {
            throw new RuntimeException('Vehicle route tidak ditemukan.');
        }

        return $loaded;
    }

    protected function buildDemandPayload(DeliveryStop $stop): array
    {
        $store = $stop->store;

        if (! $store || $store->latitude === null || $store->longitude === null) {
            throw new RuntimeException("Koordinat customer pada stop #{$stop->id} tidak valid.");
        }

        $customerId = trim((string) ($stop->source_customer_code ?: $store->code));

        if ($customerId === '') {
            throw new RuntimeException("Customer code pada stop #{$stop->id} kosong.");
        }

        return [
            'id' => (string) $stop->uuid,
            'customer_id' => $customerId,
            'customer_name' => (string) $store->name,
            'store_id' => $customerId,
            'store_name' => (string) $store->name,
            'address' => (string) ($store->address ?? ''),
            'latitude' => (float) $store->latitude,
            'longitude' => (float) $store->longitude,
            'opening_time' => $this->normalizeTime($store->opening_time),
            'closing_time' => $this->normalizeTime($store->closing_time),
            'service_duration_minutes' => (int) ($store->service_duration_minutes ?? 15),
            'so_numbers' => is_array($stop->source_so_numbers) ? $stop->source_so_numbers : [],
            'items' => [],
        ];
    }

    protected function callOptimizer(array $payload): array
    {
        $mlUrl = trim((string) config('services.ml.url'));

        if ($mlUrl === '') {
            throw new RuntimeException('Konfigurasi services.ml.url belum diisi.');
        }

        $response = Http::timeout(120)
            ->post(rtrim($mlUrl, '/') . '/optimize-existing-route', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Re-optimization service gagal: '
                . ($response->json('detail') ?? $response->body())
            );
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new RuntimeException('Response re-optimization tidak valid.');
        }

        return $result;
    }

    protected function validateOptimizerResult(array $result, Collection $stops): void
    {
        $optimized = $result['optimized_route'] ?? null;

        if (! is_array($optimized)) {
            throw new RuntimeException('Optimizer tidak mengembalikan optimized_route.');
        }

        if (count($optimized) !== $stops->count()) {
            throw new RuntimeException('Jumlah stop hasil optimizer tidak sama dengan route.');
        }

        $expected = $stops
            ->pluck('uuid')
            ->map(fn ($uuid): string => (string) $uuid)
            ->sort()
            ->values()
            ->all();

        $actual = collect($optimized)
            ->pluck('demand_id')
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values()
            ->all();

        if ($expected !== $actual) {
            throw new RuntimeException('Demand hasil optimizer tidak sesuai dengan DeliveryStop route.');
        }
    }

    protected function persistOptimization(DeliveryRoute $route, array $result): void
    {
        DB::transaction(function () use ($route, $result): void {
            $lockedRoute = DeliveryRoute::query()
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertRouteEditable($lockedRoute);

            $stops = DeliveryStop::query()
                ->where('delivery_route_id', $lockedRoute->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentSnapshot = $stops
                ->pluck('uuid')
                ->map(fn ($uuid): string => (string) $uuid)
                ->sort()
                ->values()
                ->all();

            if ($currentSnapshot !== $result['_snapshot_stop_uuids']) {
                throw new RuntimeException('DeliveryStop berubah saat re-optimization. Silakan ulangi.');
            }

            $optimizedByDemand = collect($result['optimized_route'])
                ->keyBy(fn (array $row): string => (string) $row['demand_id']);

            $sequences = $optimizedByDemand
                ->pluck('sequence')
                ->map(fn ($sequence): int => (int) $sequence)
                ->sort()
                ->values()
                ->all();

            $expectedSequences = $stops->isEmpty() ? [] : range(1, $stops->count());

            if ($sequences !== $expectedSequences) {
                throw new RuntimeException('Sequence hasil optimizer harus unik dan berurutan mulai dari 1.');
            }

            // MySQL memeriksa UNIQUE (delivery_route_id, sequence_order) pada
            // setiap UPDATE. Kosongkan semua nomor final dahulu supaya pertukaran
            // urutan (misalnya 8 menjadi 9 dan 9 menjadi 8) tidak berbenturan.
            $temporarySequence = max(0, (int) $stops->max('sequence_order'));

            foreach ($stops as $stop) {
                $stop->forceFill([
                    'sequence_order' => ++$temporarySequence,
                ])->save();
            }

            foreach ($stops as $stop) {
                $row = $optimizedByDemand->get((string) $stop->uuid);

                if (! $row) {
                    throw new RuntimeException("Hasil optimizer untuk Stop #{$stop->id} tidak ditemukan.");
                }

                $stop->forceFill([
                    'sequence_order' => (int) $row['sequence'],
                    'predicted_arrival_time' => $this->routeDateTime($lockedRoute, (string) $row['arrival_time']),
                    'predicted_service_start_time' => $this->routeDateTime($lockedRoute, (string) $row['service_start']),
                    'predicted_service_end_time' => $this->routeDateTime($lockedRoute, (string) $row['service_end']),
                    'predicted_waiting_minutes' => (int) ($row['waiting_minutes'] ?? 0),
                ])->save();
            }

            $lockedRoute->forceFill([
                'predicted_duration_minutes' => max(0, (int) ($result['route_duration_minutes'] ?? 0)),
                'predicted_package_count' => $stops->count(),
            ])->save();
        });
    }

    protected function assertRouteEditable(DeliveryRoute $route): void
    {
        if ($this->enumValue($route->status) !== 'planned') {
            throw new RuntimeException('Hanya route Planned yang dapat diubah.');
        }

        if (($route->approval_status ?? 'pending') !== 'pending') {
            throw new RuntimeException('Route sudah Approved dan tidak dapat diubah.');
        }
    }

    protected function resolveRouteStartTime(DeliveryRoute $route): string
    {
        return $this->normalizeTime($route->branch?->start_time)
            ?? self::DEFAULT_BRANCH_START_TIME;
    }

    protected function routeDateTime(DeliveryRoute $route, string $time): Carbon
    {
        $normalized = $this->normalizeTime($time);

        if (! $normalized) {
            throw new RuntimeException("Waktu optimizer tidak valid: {$time}");
        }

        return Carbon::parse($route->route_date)
            ->startOfDay()
            ->setTimeFromTimeString($normalized);
    }

    protected function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $m)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
