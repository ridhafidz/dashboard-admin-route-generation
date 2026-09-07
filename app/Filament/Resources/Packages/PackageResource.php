<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Packages\Schemas\PackageForm;
use App\Filament\Resources\Packages\Tables\PackagesTable;
use App\Models\Package;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PackageResource extends Resource
{
    protected static ?string $model =
        Package::class;

    /*
     * Package hanya dikelola melalui Sales Order.
     */
    protected static bool $shouldRegisterNavigation =
        false;

    protected static string|\UnitEnum|null $navigationGroup =
        'Operasional';

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    public static function form(
        Schema $schema
    ): Schema {
        return PackageForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return PackagesTable::configure(
            $table
        );
    }

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

    public static function getPages(): array
    {
        return [
            'index' =>
                ListPackages::route('/'),

            'create' =>
                CreatePackage::route('/create'),

            'edit' =>
                EditPackage::route(
                    '/{record}/edit'
                ),
        ];
    }
}