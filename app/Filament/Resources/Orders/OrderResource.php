<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Services\BranchContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        /*
         * Package sekarang dikelola melalui Repeater
         * pada form Sales Order.
         */
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'store.area',
            ]);

        $branchId = app(
            BranchContext::class
        )->getId();

        if ($branchId) {
            $query->whereHas(
                'store.area',
                fn (Builder $query): Builder =>
                    $query->where(
                        'branch_id',
                        $branchId
                    )
            );
        }

        return $query;
    }
}