<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\VehicleType;
use App\Enums\VehicleStatus;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_type_id')
                ->label('Vehicle Type')
                ->relationship('vehicleType', 'category')
                ->getOptionLabelFromRecordUsing(
                    fn (VehicleType $record): string => $record->category->getLabel()
                )
                ->searchable()
                ->preload()
                ->required(),

            Placeholder::make('spec_info')
                ->label('Spesifikasi')
                ->content(function (Get $get) {
                    $type = VehicleType::find($get('vehicle_type_id'));
                    if (!$type) return '-';
                    return "Tipe Box: {$type->box_type->getLabel()}, Volume: {$type->volume_m3} m³"; // ganti dari capacity_volume_m3
                }),

            TextInput::make('plate_number')
                ->label('Plate Number')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(20),

            Select::make('branch_id')
                ->label('Cabang')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('status')
                ->label('Status')
                ->options(
                    collect(VehicleStatus::cases())
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                )
                ->required(),
        ]);
    }
}