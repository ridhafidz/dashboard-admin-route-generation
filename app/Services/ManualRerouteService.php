<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ManualRerouteService
{
    public function __construct(
        protected RouteReoptimizationService $reoptimizer
    ) {
    }

    /**
     * Hanya mengambil daftar target route yang eligible.
     * Tidak memanggil OSRM / Python / OR-Tools.
     */
    public function targets(int $stopId): array
    {
        $stop = DeliveryStop::query()
            ->with([
                'store',
                'deliveryRoute.branch',
                'deliveryRoute.driver',
                'deliveryRoute.vehicle',
            ])
            ->findOrFail($stopId);

        $sourceRoute = $stop->deliveryRoute;

        if (! $sourceRoute) {
            throw new RuntimeException('Source route tidak ditemukan.');
        }

        $this->assertEditable($sourceRoute);

        $targets = DeliveryRoute::query()
            ->with([
                'driver:id,name',
                'vehicle:id,plate_number',
            ])
            ->withCount('deliveryStops')
            ->where('branch_id', $sourceRoute->branch_id)
            ->whereDate('route_date', $sourceRoute->route_date)
            ->where('status', RouteStatus::Planned->value)
            ->where('approval_status', 'pending')
            ->where('id', '!=', $sourceRoute->id)
            ->orderBy('id')
            ->get()
            ->map(fn (DeliveryRoute $route): array => [
                'id' => (int) $route->id,
                'driver_name' => $route->driver?->name ?? '-',
                'vehicle_plate' => $route->vehicle?->plate_number ?? '-',
                'stop_count' => (int) $route->delivery_stops_count,
            ])
            ->values()
            ->all();

        return [
            'stop_id' => (int) $stop->id,
            'store_name' => $stop->store?->name ?? '-',
            'customer_code' => $stop->source_customer_code ?: $stop->store?->code,
            'source_route' => [
                'id' => (int) $sourceRoute->id,
                'driver_name' => $sourceRoute->driver?->name ?? '-',
                'vehicle_plate' => $sourceRoute->vehicle?->plate_number ?? '-',
                'route_date' => $sourceRoute->route_date?->toDateString(),
            ],
            'targets' => $targets,
        ];
    }

    /**
     * Manual rerouting:
     * Admin sudah memilih target route.
     * Optimizer hanya dipanggil untuk:
     * 1. source route setelah stop dikeluarkan
     * 2. target route setelah stop dimasukkan
     */
    public function move(
        DeliveryStop|int $stop,
        DeliveryRoute|int $targetRoute
    ): array {
        $stopId = $stop instanceof DeliveryStop ? $stop->id : $stop;
        $targetRouteId = $targetRoute instanceof DeliveryRoute ? $targetRoute->id : $targetRoute;

        $stop = DeliveryStop::query()
            ->with([
                'store',
                'deliveryRoute',
            ])
            ->findOrFail($stopId);

        $sourceRoute = $this->loadRoute($stop->delivery_route_id);
        $targetRoute = $this->loadRoute($targetRouteId);

        $this->assertEditable($sourceRoute);
        $this->assertEditable($targetRoute);

        if ((int) $sourceRoute->id === (int) $targetRoute->id) {
            throw new RuntimeException('Source route dan target route tidak boleh sama.');
        }

        if ((int) $sourceRoute->branch_id !== (int) $targetRoute->branch_id) {
            throw new RuntimeException('Manual rerouting hanya dapat dilakukan pada cabang yang sama.');
        }

        if ($sourceRoute->route_date?->toDateString() !== $targetRoute->route_date?->toDateString()) {
            throw new RuntimeException('Manual rerouting hanya dapat dilakukan pada tanggal route yang sama.');
        }

        $stop = $this->loadSourceStop($sourceRoute, $stop->id);

        $sourceBeforeIds = $sourceRoute->deliveryStops
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $targetBeforeIds = $targetRoute->deliveryStops
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        /*
         * Hanya dua perhitungan optimizer.
         */
        $sourceAfterStops = $sourceRoute->deliveryStops
            ->reject(fn (DeliveryStop $item): bool => (int) $item->id === (int) $stop->id)
            ->values();

        $targetAfterStops = $targetRoute->deliveryStops
            ->concat([$stop])
            ->values();

        $sourceAfter = $this->reoptimizer->preview(
            $sourceRoute,
            $sourceAfterStops
        );

        $targetAfter = $this->reoptimizer->preview(
            $targetRoute,
            $targetAfterStops
        );

        DB::transaction(function () use (
            $sourceRoute,
            $targetRoute,
            $stop,
            $sourceBeforeIds,
            $targetBeforeIds,
            $sourceAfter,
            $targetAfter
        ): void {
            $lockedRoutes = DeliveryRoute::query()
                ->whereIn('id', [$sourceRoute->id, $targetRoute->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var DeliveryRoute|null $source */
            $source = $lockedRoutes->get($sourceRoute->id);

            /** @var DeliveryRoute|null $target */
            $target = $lockedRoutes->get($targetRoute->id);

            if (! $source || ! $target) {
                throw new RuntimeException('Source atau target route tidak ditemukan.');
            }

            $this->assertEditable($source);
            $this->assertEditable($target);

            $currentStops = DeliveryStop::query()
                ->whereIn('delivery_route_id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentSourceIds = $currentStops
                ->where('delivery_route_id', $source->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $currentTargetIds = $currentStops
                ->where('delivery_route_id', $target->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if (
                $currentSourceIds !== $sourceBeforeIds
                || $currentTargetIds !== $targetBeforeIds
            ) {
                throw new RuntimeException(
                    'Route berubah saat proses manual rerouting. Silakan ulangi.'
                );
            }

            $lockedStop = DeliveryStop::query()
                ->whereKey($stop->id)
                ->where('delivery_route_id', $source->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Gunakan nomor yang belum dipakai pada target. applyPreview()
            // kemudian akan menempatkan semua stop ke sequence final.
            $temporaryTargetSequence = max(
                0,
                (int) $currentStops
                    ->where('delivery_route_id', $target->id)
                    ->max('sequence_order')
            ) + 1;

            $lockedStop->forceFill([
                'delivery_route_id' => $target->id,
                'sequence_order' => $temporaryTargetSequence,
            ])->save();

            /*
             * Membership sudah berubah. Terapkan hasil OR-Tools source dan target.
             */
            $this->reoptimizer->applyPreview($source, $sourceAfter);
            $this->reoptimizer->applyPreview($target, $targetAfter);

            if (
                DeliveryStop::query()
                    ->where('delivery_route_id', $source->id)
                    ->count() === 0
            ) {
                $source->forceFill([
                    'status' => RouteStatus::Cancelled,
                ])->save();
            }
        });

        $movedRow = collect($targetAfter['optimized_route'] ?? [])
            ->first(
                fn (array $row): bool =>
                    (string) ($row['demand_id'] ?? '') === (string) $stop->uuid
            );

        return [
            'success' => true,
            'stop_id' => (int) $stop->id,
            'store_name' => $stop->store?->name ?? '-',
            'source_route_id' => (int) $sourceRoute->id,
            'target_route_id' => (int) $targetRoute->id,
            'new_sequence' => isset($movedRow['sequence'])
                ? (int) $movedRow['sequence']
                : null,
            'new_arrival_time' => $movedRow['arrival_time'] ?? null,
            'source_duration_minutes' => (int) ($sourceAfter['route_duration_minutes'] ?? 0),
            'target_duration_minutes' => (int) ($targetAfter['route_duration_minutes'] ?? 0),
        ];
    }

    protected function loadRoute(DeliveryRoute|int $route): DeliveryRoute
    {
        $routeId = $route instanceof DeliveryRoute ? $route->id : $route;

        return DeliveryRoute::query()
            ->with([
                'branch',
                'driver',
                'vehicle.vehicleType',
                'deliveryStops' => fn ($query) => $query->orderBy('sequence_order'),
                'deliveryStops.store',
            ])
            ->findOrFail($routeId);
    }

    protected function loadSourceStop(
        DeliveryRoute $sourceRoute,
        DeliveryStop|int $stop
    ): DeliveryStop {
        $stopId = $stop instanceof DeliveryStop ? $stop->id : $stop;

        $loaded = DeliveryStop::query()
            ->with('store')
            ->whereKey($stopId)
            ->where('delivery_route_id', $sourceRoute->id)
            ->first();

        if (! $loaded) {
            throw new RuntimeException('Delivery Stop tidak ditemukan pada source route.');
        }

        return $loaded;
    }

    protected function assertEditable(DeliveryRoute $route): void
    {
        if ($this->enumValue($route->status) !== RouteStatus::Planned->value) {
            throw new RuntimeException(
                'Manual rerouting hanya dapat digunakan pada route Planned.'
            );
        }

        if (($route->approval_status ?? 'pending') !== 'pending') {
            throw new RuntimeException(
                'Manual rerouting hanya dapat digunakan sebelum route di-Approve.'
            );
        }
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
