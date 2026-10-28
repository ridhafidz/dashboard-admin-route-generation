<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Services\BranchContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel =
        'Sales Order';

    protected static ?string $modelLabel =
        'Sales Order';

    protected static ?string $pluralModelLabel =
        'Sales Order';

    protected static string|\UnitEnum|null $navigationGroup =
        'Operasional';

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $branchId = app(
            BranchContext::class
        )->getId();

        if ($branchId) {
            $branch = DB::table('branches')
                ->where('id', $branchId)
                ->first();

            if ($branch) {
                $query->where(
                    'cabID',
                    $branch->cab_id
                );
            }
        }

        return $query
            ->selectRaw('
                MIN(visit_key) as visit_key,
                cabID,
                customerID,
                so_number,
                DATE(visit_date) as order_date,
                COUNT(DISTINCT product_id) as product_count,
                SUM(qty_cartons) as total_cartons,
                SUM(qty_packs) as total_packs,
                SUM(qty_pcs) as total_pcs,
                SUM(COALESCE(subtotal, 0)) as total_value,
                MAX(status) as status
            ')
            ->where(
                'status',
                'EFFECTIVECALL'
            )
            ->whereNotNull(
                'so_number'
            )
            ->where(
                'so_number',
                '!=',
                ''
            )
            ->groupBy(
                'cabID',
                'customerID',
                'so_number',
                DB::raw(
                    'DATE(visit_date)'
                )
            );  
    }
}