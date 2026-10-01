<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\DeliveryStopAction;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RouteControlTowerActionService
{
    public function __construct(
        protected RouteReoptimizationService $reoptimizer,
        protected ManualRerouteService $manualReroute
    ) {
    }

    public function takeout(int $stopId, ?string $reason = null): array
    {
        [$route, $stop] = $this->loadEditableStop($stopId);

        $remaining = $route->deliveryStops
            ->reject(fn (DeliveryStop $item): bool => (int) $item->id === $stopId)
            ->values();

        $preview = $this->reoptimizer->preview($route, $remaining);

        DB::transaction(function () use ($route, $stop, $reason, $preview): void {
            $lockedRoute = DeliveryRoute::query()
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStop = DeliveryStop::query()
                ->whereKey($stop->id)
                ->where('delivery_route_id', $lockedRoute->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEditable($lockedRoute);

            $this->logAction(
                actionType: 'takeout',
                route: $lockedRoute,
                stop: $lockedStop,
                reason: $reason,
                status: 'applied',
            );

            $lockedStop->delete();

            if (
                DeliveryStop::query()
                    ->where('delivery_route_id', $lockedRoute->id)
                    ->count() === 0
            ) {
                $lockedRoute->forceFill([
                    'status' => RouteStatus::Cancelled,
                ])->save();

                return;
            }

            $this->reoptimizer->applyPreview($lockedRoute, $preview);
        });

        return [
            'success' => true,
            'route_id' => (int) $route->id,
            'store_name' => $stop->store?->name,
        ];
    }

    public function reschedule(
        int $stopId,
        string $newRouteDate,
        ?string $reason = null
    ): array {
        [$route, $stop] = $this->loadEditableStop($stopId);

        $newRouteDate = Carbon::parse($newRouteDate)->toDateString();
        $oldRouteDate = $route->route_date?->toDateString();

        if (! $oldRouteDate || $newRouteDate <= $oldRouteDate) {
            throw new RuntimeException(
                'Tanggal reschedule harus setelah tanggal route saat ini.'
            );
        }

        $remaining = $route->deliveryStops
            ->reject(fn (DeliveryStop $item): bool => (int) $item->id === $stopId)
            ->values();

        $preview = $this->reoptimizer->preview($route, $remaining);

        DB::transaction(function () use (
            $route,
            $stop,
            $newRouteDate,
            $reason,
            $preview
        ): void {
            $lockedRoute = DeliveryRoute::query()
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedStop = DeliveryStop::query()
                ->whereKey($stop->id)
                ->where('delivery_route_id', $lockedRoute->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertEditable($lockedRoute);

            $this->logAction(
                actionType: 'reschedule',
                route: $lockedRoute,
                stop: $lockedStop,
                reason: $reason,
                status: 'active',
                newRouteDate: $newRouteDate,
            );

            $lockedStop->delete();

            if (
                DeliveryStop::query()
                    ->where('delivery_route_id', $lockedRoute->id)
                    ->count() === 0
            ) {
                $lockedRoute->forceFill([
                    'status' => RouteStatus::Cancelled,
                ])->save();

                return;
            }

            $this->reoptimizer->applyPreview($lockedRoute, $preview);
        });

        return [
            'success' => true,
            'route_id' => (int) $route->id,
            'store_name' => $stop->store?->name,
            'new_route_date' => $newRouteDate,
            'queued' => true,
        ];
    }

    /**
     * Hanya query database untuk menampilkan target route.
     * Tidak ada kalkulasi optimizer di tahap ini.
     */
    public function manualRerouteTargets(int $stopId): array
    {
        return $this->manualReroute->targets($stopId);
    }

    /**
     * Optimizer baru dijalankan setelah admin memilih SATU target route.
     */
    public function manualReroute(int $stopId, int $targetRouteId): array
    {
        $stop = DeliveryStop::query()
            ->with(['deliveryRoute', 'store'])
            ->findOrFail($stopId);

        $sourceRoute = $stop->deliveryRoute;

        if (! $sourceRoute) {
            throw new RuntimeException('Source route tidak ditemukan.');
        }

        $sourceRouteId = (int) $sourceRoute->id;
        $branchId = (int) $sourceRoute->branch_id;
        $storeId = $stop->store_id;
        $customerCode = $stop->source_customer_code ?: $stop->store?->code;
        $sourceDate = $sourceRoute->source_date;
        $originalRouteDate = $sourceRoute->route_date;

        $result = $this->manualReroute->move(
            $stop,
            $targetRouteId
        );

        DeliveryStopAction::query()->create([
            'delivery_stop_id' => $stop->id,
            'source_route_id' => $sourceRouteId,
            'target_route_id' => $targetRouteId,
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'customer_code' => $customerCode,
            'source_date' => $sourceDate,
            'original_route_date' => $originalRouteDate,
            'action_type' => 'reroute',
            'action_status' => 'applied',
            'created_by' => auth()->id(),
        ]);

        return $result;
    }

    protected function loadEditableStop(int $stopId): array
    {
        $stop = DeliveryStop::query()
            ->with([
                'store',
                'deliveryRoute.branch',
                'deliveryRoute.vehicle.vehicleType',
                'deliveryRoute.deliveryStops' =>
                    fn ($query) => $query->orderBy('sequence_order'),
                'deliveryRoute.deliveryStops.store',
            ])
            ->findOrFail($stopId);

        $route = $stop->deliveryRoute;

        if (! $route) {
            throw new RuntimeException('Route Delivery Stop tidak ditemukan.');
        }

        $this->assertEditable($route);

        return [$route, $stop];
    }

    protected function logAction(
        string $actionType,
        DeliveryRoute $route,
        DeliveryStop $stop,
        ?string $reason,
        string $status,
        ?string $newRouteDate = null
    ): void {
        DeliveryStopAction::query()->create([
            'delivery_stop_id' => $stop->id,
            'source_route_id' => $route->id,
            'target_route_id' => null,
            'branch_id' => $route->branch_id,
            'store_id' => $stop->store_id,
            'customer_code' =>
                $stop->source_customer_code ?: $stop->store?->code,
            'source_date' => $route->source_date,
            'original_route_date' => $route->route_date,
            'new_route_date' => $newRouteDate,
            'action_type' => $actionType,
            'reason' => $reason,
            'action_status' => $status,
            'created_by' => auth()->id(),
        ]);
    }

    protected function assertEditable(DeliveryRoute $route): void
    {
        if ($this->enumValue($route->status) !== RouteStatus::Planned->value) {
            throw new RuntimeException(
                'Hanya route Planned yang dapat dimodifikasi.'
            );
        }

        if (($route->approval_status ?? 'pending') !== 'pending') {
            throw new RuntimeException(
                'Route sudah Approved dan tidak dapat dimodifikasi.'
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
