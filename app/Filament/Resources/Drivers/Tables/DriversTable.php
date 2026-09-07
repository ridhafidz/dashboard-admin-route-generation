<?php

namespace App\Filament\Resources\Drivers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('user.email')
                ->label('Email'),
            TextColumn::make('phone'),
            TextColumn::make('branch.init_cab')
                ->label('Cabang')
                ->badge(),
            TextColumn::make('branch.lokasi_cabang')
                ->label('Lokasi')
                ->badge(),
            TextColumn::make('status')
            ->badge(),
        ]);
    }
}