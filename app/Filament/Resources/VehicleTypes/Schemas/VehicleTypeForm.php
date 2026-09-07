<?php

namespace App\Filament\Resources\VehicleTypes\Schemas;

use App\Enums\BoxType;
use App\Enums\VehicleCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')
                ->label('Kategori Kendaraan')
                ->options(
                    collect(VehicleCategory::cases())
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                )
                ->required(),

            Select::make('box_type')
                ->label('Tipe Box')
                ->options(
                    collect(BoxType::cases())
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                )
                ->required(),

            TextInput::make('length_cm')
                ->label('Panjang')
                ->numeric()
                ->minValue(0.01)
                ->suffix('cm')
                ->required(),

            TextInput::make('width_cm')
                ->label('Lebar')
                ->numeric()
                ->minValue(0.01)
                ->suffix('cm')
                ->required(),

            TextInput::make('height_cm')
                ->label('Tinggi')
                ->numeric()
                ->minValue(0.01)
                ->suffix('cm')
                ->required(),

        ]);
    }
}
