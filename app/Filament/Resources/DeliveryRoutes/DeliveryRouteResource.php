<?php

namespace App\Filament\Resources\DeliveryRoutes;

use App\Filament\Resources\DeliveryRoutes\Pages\ListDeliveryRoutes;
use App\Filament\Resources\DeliveryRoutes\Pages\ViewDeliveryRoute;
use App\Filament\Resources\DeliveryRoutes\Tables\DeliveryRoutesTable;
use App\Models\DeliveryRoute;
use App\Services\BranchContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeliveryRouteResource extends Resource
{
    protected static ?string $model =
        DeliveryRoute::class;

    protected static ?string $navigationLabel =
        'Delivery Routes';

    protected static ?string $modelLabel =
        'Delivery Route';

    protected static ?string $pluralModelLabel =
        'Delivery Routes';

    protected static string|\UnitEnum|null $navigationGroup =
        'Operasional';

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-map';

    protected static ?int $navigationSort = 20;

    public static function table(
        Table $table
    ): Table {
        return DeliveryRoutesTable::configure(
            $table
        );
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListDeliveryRoutes::route('/'),

            'view' =>
                ViewDeliveryRoute::route(
                    '/{record}'
                ),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query =
            parent::getEloquentQuery()
                ->with([
                    'branch:id,init_cab,name',

                    'driver:id,name',

                    'vehicle:id,vehicle_type_id,plate_number',

                    'vehicle.vehicleType:id,category,box_type',
                ]);

        $branchId =
            app(
                BranchContext::class
            )->getId();

        if ($branchId) {
            $query->where(
                'branch_id',
                $branchId
            );
        }

        return $query;
    }
    /*
     * Route dibuat oleh optimizer.
     *
     * Tidak boleh dibuat/edit manual
     * dari Filament.
     */

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(
        Model $record
    ): bool {
        return false;
    }

    public static function canDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}