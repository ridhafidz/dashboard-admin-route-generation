<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('tracking_number')
                ->searchable(),
            TextColumn::make('order.order_number')
                ->label('Order'),
            TextColumn::make('order.store.name')
                ->label('Toko'),
            TextColumn::make('item'),
            TextColumn::make('weight_kg')
                ->suffix(' kg'),
            IconColumn::make('is_fragile')
                ->boolean()
                ->label('Fragile'),
            TextColumn::make('scheduled_date')
                ->date(),
            TextColumn::make('status')
                ->badge(),
        ]);
    }
}