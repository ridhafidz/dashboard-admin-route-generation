<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table

            /*
            |--------------------------------------------------------------------------
            | READ FROM VIEW
            |--------------------------------------------------------------------------
            |
            | Model tetap App\Models\Order.
            |
            | Tetapi query LIST membaca:
            |
            | vw_sales_order_summary
            |
            | Alias "orders" dipertahankan agar:
            |
            | - filter
            | - sorting
            | - EditAction
            | - Order model
            |
            | tetap kompatibel.
            |
            */

            ->modifyQueryUsing(
                fn (Builder $query): Builder =>
                    $query->from(
                        'vw_sales_order_summary as orders'
                    )
            )

            ->columns([

                TextColumn::make(
                    'order_number'
                )
                    ->label('Nomor SO')
                    ->searchable()
                    ->sortable(),


                /*
                |--------------------------------------------------------------------------
                | STORE
                |--------------------------------------------------------------------------
                |
                | Tidak lagi menggunakan:
                |
                | store.name
                |
                | karena store_name sudah tersedia
                | langsung dari VIEW.
                |
                */

                TextColumn::make(
                    'store_name'
                )
                    ->label('Toko')

                    ->description(
                        fn ($record): ?string =>
                            $record
                                ->delivery_address
                    )

                    ->searchable()
                    ->sortable()
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


                /*
                |--------------------------------------------------------------------------
                | PACKAGE COUNT
                |--------------------------------------------------------------------------
                |
                | Sebelumnya:
                |
                | ->counts('packages')
                |
                | Sekarang langsung dari hasil GROUP BY VIEW.
                |
                */

                TextColumn::make(
                    'package_count'
                )
                    ->label('Jml Produk')
                    ->numeric(
                        decimalPlaces: 0
                    )
                    ->badge()
                    ->sortable(),


                /*
                |--------------------------------------------------------------------------
                | TOTAL WEIGHT
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'total_weight_kg'
                )
                    ->label('Total Berat')

                    ->numeric(
                        decimalPlaces: 4,
                        locale: 'id'
                    )

                    ->suffix(' kg')

                    ->sortable()
                    ->toggleable(),


                /*
                |--------------------------------------------------------------------------
                | TOTAL VOLUME
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'total_volume_m3'
                )
                    ->label('Total Volume')

                    ->numeric(
                        decimalPlaces: 6,
                        locale: 'id'
                    )

                    ->suffix(' m³')

                    ->sortable()
                    ->toggleable(),


                /*
                |--------------------------------------------------------------------------
                | TOTAL VALUE
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'total_order_value'
                )
                    ->label('Total Nilai')

                    ->money(
                        'IDR',
                        locale: 'id'
                    )

                    ->placeholder('-')

                    ->sortable()
                    ->toggleable(),


                TextColumn::make(
                    'status'
                )
                    ->label('Status')
                    ->badge(),
            ])

            ->filters([

                SelectFilter::make(
                    'status'
                )
                    ->label('Status')

                    ->options(
                        collect(
                            OrderStatus::cases()
                        )
                            ->mapWithKeys(
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