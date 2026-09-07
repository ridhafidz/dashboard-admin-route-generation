<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([
                TextColumn::make(
                    'order_number'
                )
                    ->label('Nomor SO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make(
                    'store.name'
                )
                    ->label('Toko')
                    ->description(
                        fn ($record): ?string =>
                            $record
                                ->store
                                ?->address
                    )
                    ->searchable()
                    ->wrap(),

                TextColumn::make(
                    'order_date'
                )
                    ->label('Tanggal Order')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make(
                    'scheduled_date'
                )
                    ->label('Tanggal Kirim')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make(
                    'packages_count'
                )
                    ->counts('packages')
                    ->label('Jml Produk')
                    ->badge(),

                TextColumn::make(
                    'packages_sum_weight_kg'
                )
                    ->sum(
                        'packages',
                        'weight_kg'
                    )
                    ->label('Total Berat')
                    ->numeric(
                        decimalPlaces: 4,
                        locale: 'id'
                    )
                    ->suffix(' kg')
                    ->toggleable(),

                TextColumn::make(
                    'packages_sum_volume_m3'
                )
                    ->sum(
                        'packages',
                        'volume_m3'
                    )
                    ->label('Total Volume')
                    ->numeric(
                        decimalPlaces: 6,
                        locale: 'id'
                    )
                    ->suffix(' m³')
                    ->toggleable(),

                TextColumn::make(
                    'packages_sum_total_price'
                )
                    ->sum(
                        'packages',
                        'total_price'
                    )
                    ->label('Total Nilai')
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        collect(
                            OrderStatus::cases()
                        )->mapWithKeys(
                            fn (
                                OrderStatus $case
                            ) => [
                                $case->value =>
                                    $case->getLabel(),
                            ]
                        )
                    ),
            ])
            ->defaultSort(
                'scheduled_date',
                'desc'
            )
            ->recordActions([
                EditAction::make(),
            ]);
    }
}