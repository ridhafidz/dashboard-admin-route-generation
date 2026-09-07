<?php

namespace App\Filament\Resources\Stores\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('code')->searchable(),
            TextColumn::make('area.name')->label('Area'),
            TextColumn::make('address')->limit(40),
            TextColumn::make('opening_time')->label('Jam Buka'),
            TextColumn::make('closing_time')->label('Jam Tutup'),
        ]);
    }
}