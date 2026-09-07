<?php

namespace App\Filament\Resources\Vehicles\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plate_number')
                    ->label('Plate Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehicleType.category')
                    ->label('Vehicle Type')
                    ->badge()
                    ->searchable(),

                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ]);
    }
}