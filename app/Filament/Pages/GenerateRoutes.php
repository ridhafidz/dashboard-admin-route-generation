<?php

namespace App\Filament\Pages;

use App\Enums\DriverStatus;
use App\Enums\PackageStatus;
use App\Enums\RouteStatus;
use App\Enums\VehicleStatus;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Package;
use App\Models\Store;
use App\Models\Vehicle;
use App\Services\BranchContext;
use App\Services\RouteGenerationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use UnitEnum;

class GenerateRoutes extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $title = 'Generate Rute Hari Ini';

    protected string $view = 'filament.pages.generate-routes';

    public ?int $readyDriverCount = null;

    public ?int $readyVehicleCount = null;

    public ?array $result = null;

    public array $branchMarkers = [];

    public array $storeMarkers = [];

    public function mount(): void
    {
        $activeBranchId = app(BranchContext::class)->getId();

        $preview = $activeBranchId
            ? $this->getTodayRoutePreview($activeBranchId)
            : ['drivers' => 0, 'vehicles' => 0];

        $this->readyDriverCount = $preview['drivers'];
        $this->readyVehicleCount = $preview['vehicles'];

        $this->branchMarkers = Branch::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get([
                'id',
                'name',
                'cab_id',
                'init_cab',
                'region_id',
                'latitude',
                'longitude',
            ])
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'cab_id' => $branch->cab_id,
                'init_cab' => $branch->init_cab,
                'region_id' => $branch->region_id,
                'latitude' => (float) $branch->latitude,
                'longitude' => (float) $branch->longitude,
            ])
            ->values()
            ->all();

        $this->storeMarkers = Store::query()
            ->with('area')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get([
                'id',
                'name',
                'code',
                'area_id',
                'latitude',
                'longitude',
            ])
            ->map(fn ($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'area_name' => $store->area?->code,
                'latitude' => (float) $store->latitude,
                'longitude' => (float) $store->longitude,
            ])
            ->values()
            ->all();
    }

    public function generate(array $data): void
    {
        try {
            $result = app(RouteGenerationService::class)->generateTodayRoutes($data);
            $this->result = $result;

            Notification::make()
                ->title('Berhasil generate route')
                ->body(
                    "Berhasil membuat {$result['route_count']} route untuk "
                    . "{$result['driver_count']} driver dan {$result['package_count']} package."
                )
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal generate route')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('generateRoute')
                ->label('Generate Route Hari Ini')
                ->icon('heroicon-o-map')
                ->color('warning')
                ->modalHeading('Generate Route Hari Ini')
                ->modalDescription(
                    'Hanya driver Ready, kendaraan Active, dan package Pending pada cabang yang dipilih yang akan diproses.'
                )
                ->modalSubmitActionLabel('Generate Route')
                ->modalCancelActionLabel('Batal')
                ->modalWidth('2xl')
                ->form([
                    Select::make('branch_id')
                        ->label('Cabang')
                        ->options(
                            Branch::query()
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Branch $branch) => [
                                    $branch->id => "{$branch->init_cab} - {$branch->name}",
                                ])
                        )
                        ->default(app(BranchContext::class)->getId())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),

                    Section::make('Preview')
                        ->description('Preview hanya menghitung data pada cabang yang dipilih.')
                        ->schema([
                            TextEntry::make('package_count')
                                ->label('Package Pending')
                                ->state(function (Get $get) {
                                    $branchId = (int) ($get('branch_id') ?? 0);

                                    return $this->getTodayRoutePreview($branchId)['packages'];
                                })
                                ->badge()
                                ->color('warning'),

                            TextEntry::make('store_count')
                                ->label('Store Tujuan')
                                ->state(function (Get $get) {
                                    $branchId = (int) ($get('branch_id') ?? 0);

                                    return $this->getTodayRoutePreview($branchId)['stores'];
                                })
                                ->badge()
                                ->color('success'),

                            TextEntry::make('driver_count')
                                ->label('Driver Ready')
                                ->state(function (Get $get) {
                                    $branchId = (int) ($get('branch_id') ?? 0);

                                    return $this->getTodayRoutePreview($branchId)['drivers'];
                                })
                                ->badge()
                                ->color('info'),

                            TextEntry::make('vehicle_count')
                                ->label('Vehicle Active')
                                ->state(function (Get $get) {
                                    $branchId = (int) ($get('branch_id') ?? 0);

                                    return $this->getTodayRoutePreview($branchId)['vehicles'];
                                })
                                ->badge()
                                ->color('primary'),
                        ])
                        ->columns(4),
                ])
                ->action(function (array $data): void {
                    $this->generate($data);
                }),
        ];
    }

    protected function getTodayRoutePreview(int $branchId): array
    {
        if ($branchId <= 0) {
            return [
                'packages' => 0,
                'stores' => 0,
                'drivers' => 0,
                'vehicles' => 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ELIGIBLE PACKAGE
        |--------------------------------------------------------------------------
        | Jadwal utama sekarang berasal dari:
        |
        | orders.scheduled_date
        |
        | BUKAN packages.scheduled_date.
        |
        */

        $eligiblePackages = Package::query()
            ->where(
                'packages.status',
                PackageStatus::Pending->value
            )
            ->whereHas(
                'order',
                function ($query) {
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
                function ($query) use ($branchId) {
                    $query->where(
                        'branch_id',
                        $branchId
                    );
                }
            );

        /*
        |--------------------------------------------------------------------------
        | STORE COUNT
        |--------------------------------------------------------------------------
        |
        | Jangan membuat logika store terpisah.
        |
        | Ambil store langsung dari package yang benar-benar eligible
        | supaya Package Pending dan Store Tujuan selalu sinkron.
        |
        */

        $storeCount = Package::query()
            ->where(
                'packages.status',
                PackageStatus::Pending->value
            )
            ->whereHas(
                'order',
                function ($query) {
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
                function ($query) use ($branchId) {
                    $query->where(
                        'branch_id',
                        $branchId
                    );
                }
            )
            ->join(
                'orders',
                'orders.id',
                '=',
                'packages.order_id'
            )
            ->distinct()
            ->count(
                'orders.store_id'
            );

        /*
        |--------------------------------------------------------------------------
        | RESOURCE YANG SUDAH TERPAKAI HARI INI
        |--------------------------------------------------------------------------
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

        return [
            'packages' => (clone $eligiblePackages)->count(),

            'stores' => $storeCount,

            'drivers' => Driver::query()
                ->where(
                    'branch_id',
                    $branchId
                )
                ->where(
                    'status',
                    DriverStatus::Ready->value
                )
                ->whereNotIn(
                    'id',
                    $usedDriverIds
                )
                ->count(),

            'vehicles' => Vehicle::query()
                ->where(
                    'branch_id',
                    $branchId
                )
                ->where(
                    'status',
                    VehicleStatus::Active->value
                )
                ->whereNotIn(
                    'id',
                    $usedVehicleIds
                )
                ->count(),
        ];
    }
}
