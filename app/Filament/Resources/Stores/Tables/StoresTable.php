<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Services\CustomerSourceService as Src;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /*
            |------------------------------------------------------------------
            | CUSTOMER SOURCE - READ ONLY
            |------------------------------------------------------------------
            |
            | Hanya field yang dibutuhkan aplikasi yang ditampilkan.
            | Tidak ada lagi Channel, Market Segment, Status, Salesman, dll.
            |
            | PREP INTEGRASI SISTEM UTAMA:
            | Nama kolom source tetap dipusatkan di CustomerSourceService.
            |
            */
            ->columns([
                TextColumn::make(Src::COL_CUSTOMER_ID)
                    ->label('Customer ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make(Src::COL_CUSTOMER_NAME)
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable()
                    ->limit(35)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('-'),

                TextColumn::make(Src::COL_ADDRESS)
                    ->label('Address')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('-'),

                TextColumn::make(Src::COL_CITY)
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make(Src::COL_PHONE)
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make(Src::COL_RECEIVER_NAME)
                    ->label('Penerima')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make(Src::COL_AREA)
                    ->label('Area')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make(Src::COL_SUB_AREA)
                    ->label('Sub Area')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('coordinates')
                    ->label('Longlat')
                    ->state(function ($record): string {
                        $lat = trim((string) ($record->{Src::COL_LATITUDE} ?? ''));
                        $lng = trim((string) ($record->{Src::COL_LONGITUDE} ?? ''));

                        if ($lat === '' || $lng === '') {
                            return '-';
                        }

                        return $lat . ', ' . $lng;
                    })
                    ->copyable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(Src::COL_CUSTOMER_NAME)
            ->striped();
    }
}
