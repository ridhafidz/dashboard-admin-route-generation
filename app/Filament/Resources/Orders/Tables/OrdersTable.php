<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('so_number')
                    ->label('Nomor SO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customerID')
                    ->label('Kode Toko')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->label('Tanggal SO')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('product_count')
                    ->label('Jml Produk')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('total_cartons')
                    ->label('Total Karton')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('total_packs')
                    ->label('Total Pack')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('total_pcs')
                    ->label('Total PCS')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('total_value')
                    ->label('Total Nilai')
                    ->money('IDR')
                    ->sortable(),

            ])
            ->defaultSort(
                'order_date',
                'desc'
            )
            ->recordActions([])
            ->bulkActions([]);
    }
}