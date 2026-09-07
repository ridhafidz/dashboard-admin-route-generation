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

                TextColumn::make('unit_weight_kg')
                    ->label('Berat/PCS')
                    ->numeric(
                        decimalPlaces: 4,
                        locale: 'id'
                    )
                    ->suffix(' kg')
                    ->toggleable(),

                TextColumn::make('carton_weight_kg')
                    ->label('Berat/Karton')
                    ->numeric(
                        decimalPlaces: 4,
                        locale: 'id'
                    )
                    ->suffix(' kg')
                    ->toggleable(),

                TextColumn::make('unit_volume_m3')
                    ->label('Volume/PCS')
                    ->numeric(
                        decimalPlaces: 8,
                        locale: 'id'
                    )
                    ->suffix(' m³')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('carton_volume_m3')
                    ->label('Volume/Karton')
                    ->numeric(
                        decimalPlaces: 6,
                        locale: 'id'
                    )
                    ->suffix(' m³')
                    ->toggleable(),

                TextColumn::make('unit_price')
                    ->label('Harga/PCS')
                    ->money(
                        'IDR',
                        locale: 'id'
                    )
                    ->placeholder('-')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('carton_price')
                    ->label('Harga/Karton')
                    ->money(
                        'IDR',
                        locale: 'id'
                    )
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
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

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        collect(ProductStatus::cases())
                            ->mapWithKeys(
                                fn (ProductStatus $case) => [
                                    $case->value =>
                                        $case->getLabel(),
                                ]
                            )
                    ),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}