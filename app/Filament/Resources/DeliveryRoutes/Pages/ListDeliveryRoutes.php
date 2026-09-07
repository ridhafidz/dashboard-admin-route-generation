<?php

namespace App\Filament\Resources\DeliveryRoutes\Pages;

use App\Enums\DriverStatus;
use App\Enums\OrderStatus;
use App\Enums\PackageStatus;
use App\Enums\RouteStatus;
use App\Enums\VehicleStatus;
use App\Filament\Resources\DeliveryRoutes\DeliveryRouteResource;
use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Package;
use App\Models\Vehicle;
use App\Services\BranchContext;
use App\Services\RouteGenerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ListDeliveryRoutes extends ListRecords
{
    protected static string $resource =
        DeliveryRouteResource::class;


    protected function getHeaderActions(): array
    {
        return [

            Action::make('generateRoute')

                ->label('Generate Route Hari Ini')

                ->icon(
                    'heroicon-o-sparkles'
                )

                ->color('warning')

                ->modalHeading(
                    'Generate Route Hari Ini'
                )

                ->modalDescription(
                    'Sales Order Pending akan dikelompokkan '
                    . 'berdasarkan box type, kedekatan antar toko, '
                    . 'kapasitas volume kendaraan, kemudian '
                    . 'diurutkan dengan OR-Tools.'
                )

                ->modalSubmitActionLabel(
                    'Generate Route'
                )

                ->modalCancelActionLabel(
                    'Batal'
                )

                ->modalWidth('2xl')

                ->form([

                    Select::make('branch_id')

                        ->label('Cabang')

                        ->options(

                            Branch::query()
                                ->where(
                                    'status',
                                    'active'
                                )
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(
                                    fn (
                                        Branch $branch
                                    ) => [

                                        $branch->id =>
                                            "{$branch->init_cab} - {$branch->name}",
                                    ]
                                )
                        )

                        ->default(
                            app(
                                BranchContext::class
                            )->getId()
                        )

                        ->searchable()

                        ->preload()

                        ->live()

                        ->required(),


                    Section::make('Preview')

                        ->description(
                            'Data yang memenuhi syarat '
                            . 'untuk proses routing.'
                        )

                        ->schema([

                            TextEntry::make(
                                'package_count'
                            )

                                ->label(
                                    'Package Pending'
                                )

                                ->state(
                                    function (
                                        Get $get
                                    ) {

                                        $branchId =
                                            (int) (
                                                $get(
                                                    'branch_id'
                                                )
                                                ?? 0
                                            );

                                        return $this
                                            ->getTodayRoutePreview(
                                                $branchId
                                            )[
                                                'packages'
                                            ];
                                    }
                                )

                                ->badge()

                                ->color('warning'),


                            TextEntry::make(
                                'store_count'
                            )

                                ->label(
                                    'Store Tujuan'
                                )

                                ->state(
                                    function (
                                        Get $get
                                    ) {

                                        $branchId =
                                            (int) (
                                                $get(
                                                    'branch_id'
                                                )
                                                ?? 0
                                            );

                                        return $this
                                            ->getTodayRoutePreview(
                                                $branchId
                                            )[
                                                'stores'
                                            ];
                                    }
                                )

                                ->badge()

                                ->color('success'),


                            TextEntry::make(
                                'driver_count'
                            )

                                ->label(
                                    'Driver Ready'
                                )

                                ->state(
                                    function (
                                        Get $get
                                    ) {

                                        $branchId =
                                            (int) (
                                                $get(
                                                    'branch_id'
                                                )
                                                ?? 0
                                            );

                                        return $this
                                            ->getTodayRoutePreview(
                                                $branchId
                                            )[
                                                'drivers'
                                            ];
                                    }
                                )

                                ->badge()

                                ->color('info'),


                            TextEntry::make(
                                'vehicle_count'
                            )

                                ->label(
                                    'Vehicle Active'
                                )

                                ->state(
                                    function (
                                        Get $get
                                    ) {

                                        $branchId =
                                            (int) (
                                                $get(
                                                    'branch_id'
                                                )
                                                ?? 0
                                            );

                                        return $this
                                            ->getTodayRoutePreview(
                                                $branchId
                                            )[
                                                'vehicles'
                                            ];
                                    }
                                )

                                ->badge()

                                ->color('primary'),
                        ])

                        ->columns(4),
                ])

                ->action(
                    function (
                        array $data
                    ): void {

                        try {

                            $result =
                                app(
                                    RouteGenerationService::class
                                )
                                ->generateTodayRoutes(
                                    $data
                                );


                            Notification::make()

                                ->title(
                                    'Berhasil Generate Route'
                                )

                                ->body(
                                    "Berhasil membuat "
                                    . "{$result['route_count']} route, "
                                    . "{$result['package_count']} package, "
                                    . "dan {$result['demand_count']} delivery demand."
                                )

                                ->success()

                                ->send();


                            /*
                             * Refresh tabel Delivery Routes
                             * setelah berhasil.
                             */
                            $this->resetTable();

                        } catch (
                            \Throwable $e
                        ) {

                            Notification::make()

                                ->title(
                                    'Gagal Generate Route'
                                )

                                ->body(
                                    $e->getMessage()
                                )

                                ->danger()

                                ->send();
                        }
                    }
                ),
        ];
    }


    protected function getTodayRoutePreview(
        int $branchId
    ): array {

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
        */

        $eligiblePackages =
            Package::query()

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
                    function (
                        $query
                    ) use (
                        $branchId
                    ) {

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
        */

        $storeCount =
            (clone $eligiblePackages)

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
        | RESOURCE YANG SUDAH TERPAKAI
        |--------------------------------------------------------------------------
        */

        $usedDriverIds =
            DeliveryRoute::query()

                ->whereDate(
                    'route_date',
                    today()
                )

                ->where(
                    'status',
                    '!=',
                    RouteStatus::Cancelled->value
                )

                ->pluck(
                    'driver_id'
                );


        $usedVehicleIds =
            DeliveryRoute::query()

                ->whereDate(
                    'route_date',
                    today()
                )

                ->where(
                    'status',
                    '!=',
                    RouteStatus::Cancelled->value
                )

                ->pluck(
                    'vehicle_id'
                );


        return [

            'packages' =>
                (clone $eligiblePackages)
                    ->count(),


            'stores' =>
                $storeCount,


            'drivers' =>
                Driver::query()

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


            'vehicles' =>
                Vehicle::query()

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