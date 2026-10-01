<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SmartRerouteService
{
    public function __construct(
        protected RouteReoptimizationService $reoptimizer
    ) {
    }

    public function recommendations(
        DeliveryRoute|int $sourceRoute,
        DeliveryStop|int $stop
    ): array {
        $sourceRoute = $this->loadRoute($sourceRoute);
        $this->assertEditable($sourceRoute);
        $stop = $this->loadSourceStop($sourceRoute, $stop);

        $sourceBefore = $this->reoptimizer->preview($sourceRoute);
        $sourceAfterStops = $sourceRoute->deliveryStops
            ->reject(fn (DeliveryStop $item): bool => (int) $item->id === (int) $stop->id)
            ->values();
        $sourceAfter = $this->reoptimizer->preview($sourceRoute, $sourceAfterStops);

        $targetRoutes = DeliveryRoute::query()
            ->with([
                'branch',
                'driver',
                'vehicle.vehicleType',
                'deliveryStops' => fn ($query) => $query->orderBy('sequence_order'),
                'deliveryStops.store',
            ])
            ->where('branch_id', $sourceRoute->branch_id)
            ->whereDate('route_date', $sourceRoute->route_date)
            ->where('status', RouteStatus::Planned->value)
            ->where('approval_status', 'pending')
            ->where('id', '!=', $sourceRoute->id)
            ->orderBy('id')
            ->get();

        $candidates = [];
        $rejected = [];

        foreach ($targetRoutes as $targetRoute) {
            try {
                $targetBefore = $this->reoptimizer->preview($targetRoute);
                $targetAfterStops = $targetRoute->deliveryStops
                    ->concat([$stop])
                    ->values();
                $targetAfter = $this->reoptimizer->preview($targetRoute, $targetAfterStops);

                $beforeKm = $this->floatMetric($sourceBefore, 'total_distance_km')
                    + $this->floatMetric($targetBefore, 'total_distance_km');
                $afterKm = $this->floatMetric($sourceAfter, 'total_distance_km')
                    + $this->floatMetric($targetAfter, 'total_distance_km');

                $beforeMinutes = $this->intMetric($sourceBefore, 'route_duration_minutes')
                    + $this->intMetric($targetBefore, 'route_duration_minutes');
                $afterMinutes = $this->intMetric($sourceAfter, 'route_duration_minutes')
                    + $this->intMetric($targetAfter, 'route_duration_minutes');

                $inserted = collect($targetAfter['optimized_route'] ?? [])
                    ->first(fn (array $row): bool =>
                        (string) ($row['demand_id'] ?? '') === (string) $stop->uuid
                    );

                $candidates[] = [
                    'target_route_id' => (int) $targetRoute->id,
                    'driver_name' => $targetRoute->driver?->name ?? '-',
                    'vehicle_plate' => $targetRoute->vehicle?->plate_number ?? '-',
                    'suggested_sequence' => $inserted['sequence'] ?? null,
                    'suggested_arrival_time' => $inserted['arrival_time'] ?? null,
                    'target_extra_distance_km' => round(
                        $this->floatMetric($targetAfter, 'total_distance_km')
                        - $this->floatMetric($targetBefore, 'total_distance_km'),
                        2
                    ),
                    'target_extra_duration_minutes' =>
                        $this->intMetric($targetAfter, 'route_duration_minutes')
                        - $this->intMetric($targetBefore, 'route_duration_minutes'),
                    'combined_distance_before_km' => round($beforeKm, 2),
                    'combined_distance_after_km' => round($afterKm, 2),
                    'combined_duration_before_minutes' => $beforeMinutes,
                    'combined_duration_after_minutes' => $afterMinutes,
                    'net_distance_delta_km' => round($afterKm - $beforeKm, 2),
                    'net_duration_delta_minutes' => $afterMinutes - $beforeMinutes,
                    'source_after' => $sourceAfter,
                    'target_after' => $targetAfter,
                ];
            } catch (\Throwable $e) {
                $rejected[] = [
                    'target_route_id' => (int) $targetRoute->id,
                    'driver_name' => $targetRoute->driver?->name ?? '-',
                    'vehicle_plate' => $targetRoute->vehicle?->plate_number ?? '-',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $candidates = collect($candidates)
            ->sort(fn (array $a, array $b): int => [
                $a['net_duration_delta_minutes'],
                $a['net_distance_delta_km'],
            ] <=> [
                $b['net_duration_delta_minutes'],
                $b['net_distance_delta_km'],
            ])
            ->values()
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;
                return $row;
            })
            ->all();

        return [
            'source_route_id' => (int) $sourceRoute->id,
            'stop_id' => (int) $stop->id,
            'store_name' => $stop->store?->name,
            'customer_code' => $stop->source_customer_code ?: $stop->store?->code,
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'rejected' => $rejected,
        ];
    }

    public function executeReroute(
        DeliveryRoute|int $sourceRoute,
        DeliveryStop|int $stop,
        DeliveryRoute|int $targetRoute
    ): array {
        $sourceRoute = $this->loadRoute($sourceRoute);
        $targetRoute = $this->loadRoute($targetRoute);
        $this->assertEditable($sourceRoute);
        $this->assertEditable($targetRoute);

        if ((int) $sourceRoute->id === (int) $targetRoute->id) {
            throw new RuntimeException('Source Route dan Target Route tidak boleh sama.');
        }

        if ((int) $sourceRoute->branch_id !== (int) $targetRoute->branch_id) {
            throw new RuntimeException('Reroute hanya dapat dilakukan pada cabang yang sama.');
        }

        if ($sourceRoute->route_date?->toDateString() !== $targetRoute->route_date?->toDateString()) {
            throw new RuntimeException('Reroute hanya dapat dilakukan pada tanggal route yang sama.');
        }

        $stop = $this->loadSourceStop($sourceRoute, $stop);

        $sourceBeforeIds = $sourceRoute->deliveryStops->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $targetBeforeIds = $targetRoute->deliveryStops->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $sourceAfter = $this->reoptimizer->preview(
            $sourceRoute,
            $sourceRoute->deliveryStops
                ->reject(fn (DeliveryStop $item): bool => (int) $item->id === (int) $stop->id)
                ->values()
        );

        $targetAfter = $this->reoptimizer->preview(
            $targetRoute,
            $targetRoute->deliveryStops->concat([$stop])->values()
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

            $source = $lockedRoutes->get($sourceRoute->id);
            $target = $lockedRoutes->get($targetRoute->id);

            if (! $source || ! $target) {
                throw new RuntimeException('Source/target route tidak ditemukan.');
            }

            $this->assertEditable($source);
            $this->assertEditable($target);

            $current = DeliveryStop::query()
                ->whereIn('delivery_route_id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $currentSourceIds = $current->where('delivery_route_id', $source->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $currentTargetIds = $current->where('delivery_route_id', $target->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($currentSourceIds !== $sourceBeforeIds || $currentTargetIds !== $targetBeforeIds) {
                throw new RuntimeException('Route berubah saat Smart Reroute diproses. Silakan ulangi.');
            }

            $lockedStop = DeliveryStop::query()
                ->whereKey($stop->id)
                ->where('delivery_route_id', $source->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStop->forceFill([
                'delivery_route_id' => $target->id,
                'sequence_order' => 0,
            ])->save();

            $this->reoptimizer->applyPreview($source, $sourceAfter);
            $this->reoptimizer->applyPreview($target, $targetAfter);

            if (DeliveryStop::query()->where('delivery_route_id', $source->id)->count() === 0) {
                $source->forceFill(['status' => RouteStatus::Cancelled])->save();
            }
        });

        return [
            'success' => true,
            'stop_id' => (int) $stop->id,
            'store_name' => $stop->store?->name,
            'source_route_id' => (int) $sourceRoute->id,
            'target_route_id' => (int) $targetRoute->id,
            'source_after' => $this->stripInternal($sourceAfter),
            'target_after' => $this->stripInternal($targetAfter),
        ];
    }

    protected function loadRoute(DeliveryRoute|int $route): DeliveryRoute
    {
        $id = $route instanceof DeliveryRoute ? $route->id : $route;

        return DeliveryRoute::query()
            ->with([
                'branch',
                'driver',
                'vehicle.vehicleType',
                'deliveryStops' => fn ($query) => $query->orderBy('sequence_order'),
                'deliveryStops.store',
            ])
            ->findOrFail($id);
    }

    protected function loadSourceStop(DeliveryRoute $route, DeliveryStop|int $stop): DeliveryStop
    {
        $id = $stop instanceof DeliveryStop ? $stop->id : $stop;

        $loaded = DeliveryStop::query()
            ->with('store')
            ->whereKey($id)
            ->where('delivery_route_id', $route->id)
            ->first();

        if (! $loaded) {
            throw new RuntimeException('Delivery Stop tidak ditemukan pada source route.');
        }

        return $loaded;
    }

    protected function assertEditable(DeliveryRoute $route): void
    {
        if ($this->enumValue($route->status) !== RouteStatus::Planned->value) {
            throw new RuntimeException('Smart Reroute hanya dapat digunakan pada route Planned.');
        }

        if (($route->approval_status ?? 'pending') !== 'pending') {
            throw new RuntimeException('Smart Reroute hanya dapat digunakan sebelum route di-Approve.');
        }
    }

    protected function stripInternal(array $value): array
    {
        unset($value['_snapshot_stop_uuids']);
        return $value;
    }

    protected function floatMetric(array $result, string $key): float
    {
        return (float) ($result[$key] ?? 0);
    }

    protected function intMetric(array $result, string $key): int
    {
        return (int) ($result[$key] ?? 0);
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
