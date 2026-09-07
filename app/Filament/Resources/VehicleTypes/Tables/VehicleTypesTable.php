<?php

namespace App\Filament\Resources\VehicleTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->searchable(),

                TextColumn::make('box_type')
                    ->label('Box Type')
                    ->badge(),

                TextColumn::make('length_cm')
                    ->label('Panjang')
                    ->suffix(' cm'),

                TextColumn::make('width_cm')
                    ->label('Lebar')
                    ->suffix(' cm'),

                TextColumn::make('height_cm')
                    ->label('Tinggi')
                    ->suffix(' cm'),

                TextColumn::make('volume_m3')
                    ->label('Volume')
                    ->suffix(' m³')
                    
            ])
            ->filters([
                //
            ]) 
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}