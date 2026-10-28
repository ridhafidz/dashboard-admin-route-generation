<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\BoxType;
use App\Enums\ProductStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('box_type')
                    ->label('Tipe Box')
                    ->badge(),

                TextColumn::make('units_per_carton')
                    ->label('PCS/Karton')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),

                TextColumn::make('units_per_pack')
                    ->label('PCS/Pack')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),

                TextColumn::make('unit_volume_cm3')
                    ->label('Volume/PCS')
                    ->numeric(
                        decimalPlaces: 2,
                        locale: 'id'
                    )
                    ->suffix(' cm³')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('carton_volume_cm3')
                    ->label('Volume/Carton')
                    ->numeric(
                        decimalPlaces: 2,
                        locale: 'id'
                    )
                    ->suffix(' cm³')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('box_type')
                    ->label('Tipe Box')
                    ->options(
                        collect(BoxType::cases())
                            ->mapWithKeys(
                                fn (BoxType $case) => [
                                    $case->value =>
                                        $case->getLabel(),
                                ]
                            )
                    ),
                
                SelectFilter::make('code')
                    ->label('Kode'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}