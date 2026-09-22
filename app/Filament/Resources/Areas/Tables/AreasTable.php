<?php

namespace App\Filament\Resources\Areas\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class AreasTable
{
    public static function configure(
        Table $table
    ): Table {

        return $table
            ->columns([

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stores_count')
                    ->label('Jumlah Store')
                    ->counts('stores')
                    ->formatStateUsing(
                        fn ($state): string =>
                            (int) $state
                            . ' Store'
                    )
                    ->badge()
                    ->sortable(),

                TextColumn::make('stores.name')
                    ->label('Anggota Area')
                    ->placeholder(
                        'Belum ada store'
                    )
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->expandableLimitedList(),
            ])

            ->recordActions([
                ViewAction::make()
                    ->label('Detail')
                    ->icon('heroicon-o-map')
                    ->color('info'),
            ])

            ->defaultSort(
                'code',
                'asc'
            );
    }
}