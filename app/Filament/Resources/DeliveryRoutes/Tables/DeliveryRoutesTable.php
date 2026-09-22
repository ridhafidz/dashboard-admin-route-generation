<?php

namespace App\Filament\Resources\DeliveryRoutes\Tables;

use App\Enums\RouteStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DeliveryRoutesTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([

                TextColumn::make(
                    'id'
                )
                    ->label('Route')

                    ->formatStateUsing(
                        fn ($state): string =>
                            '#' . $state
                    )

                    ->sortable(),


                TextColumn::make(
                    'route_date'
                )
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),


                /*
                |--------------------------------------------------------------------------
                | BRANCH
                |--------------------------------------------------------------------------
                |
                | Tidak perlu mengambil branch.init_cab
                | untuk nama kolom utamanya.
                |
                | VIEW sudah mempunyai:
                |
                | branch_initial
                | branch_name
                |
                */

                TextColumn::make(
                    'branch_initial'
                )
                    ->label('Cabang')

                    ->description(
                        fn ($record): ?string =>
                            $record
                                ->branch_name
                    )

                    ->searchable()
                    ->sortable(),


                /*
                |--------------------------------------------------------------------------
                | DRIVER
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'driver_name'
                )
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),


                /*
                |--------------------------------------------------------------------------
                | VEHICLE
                |--------------------------------------------------------------------------
                |
                | Plate sudah berasal dari VIEW.
                |
                | Description vehicle type sementara
                | tetap menggunakan relationship existing,
                | supaya tampilan tidak berubah.
                |
                */

                TextColumn::make(
                    'plate_number'
                )
                    ->label('Vehicle')

                    ->description(
                        function ($record): ?string {

                            $category =
                                $record->vehicle_category

                                    ? Str::of(
                                        $record->vehicle_category
                                    )
                                    ->replace('_', ' ')
                                    ->title()
                                    ->toString()

                                : '-';

                            $box =
                                $record->vehicle_box_type

                                    ? Str::of(
                                        $record->vehicle_box_type
                                    )
                                    ->replace('_', ' ')
                                    ->title()
                                    ->toString()

                                : '-';

                            return
                                $category
                                . ' - '
                                . $box;
                        }
                    )

                    ->searchable()
                    ->sortable(),

                /*
                |--------------------------------------------------------------------------
                | STOP COUNT
                |--------------------------------------------------------------------------
                |
                | Sebelumnya:
                |
                | ->counts('deliveryStops')
                |
                | Sekarang:
                |
                | total_stops dari VIEW.
                |
                */

                TextColumn::make(
                    'total_stops'
                )
                    ->label('Stop')

                    ->numeric(
                        decimalPlaces: 0
                    )

                    ->badge()
                    ->sortable(),


                TextColumn::make(
                    'predicted_package_count'
                )
                    ->label('Package')

                    ->numeric(
                        decimalPlaces: 0
                    )

                    ->badge()
                    ->sortable(),


                TextColumn::make(
                    'predicted_duration_minutes'
                )
                    ->label('Prediksi Durasi')

                    ->formatStateUsing(
                        function ($state): string {

                            $minutes =
                                (int) (
                                    $state ?? 0
                                );


                            $hours =
                                intdiv(
                                    $minutes,
                                    60
                                );


                            $remaining =
                                $minutes % 60;


                            if ($hours <= 0) {

                                return
                                    "{$remaining} menit";
                            }


                            return
                                "{$hours} jam "
                                . "{$remaining} menit";
                        }
                    ),


                TextColumn::make(
                    'status'
                )
                    ->label('Status')
                    ->badge(),


                /*
                |--------------------------------------------------------------------------
                | APPROVAL STATUS
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'approval_status'
                )
                    ->label('Approval')

                    ->badge()

                    ->formatStateUsing(
                        fn ($state): string =>
                            match ($state) {
                                'approved' => 'Approved',
                                'pending'  => 'Pending',
                                default    => ucfirst((string) $state),
                            }
                    )

                    ->color(
                        fn ($state): string =>
                            match ($state) {
                                'approved' => 'success',
                                'pending'  => 'warning',
                                default    => 'gray',
                            }
                    )

                    ->sortable(),
            ])

            ->filters([

                SelectFilter::make(
                    'status'
                )
                    ->label('Status')

                    ->default(
                        '__active__'
                    )

                    ->options(
                        array_merge(

                            [
                                '__active__' =>
                                    'Aktif',

                                '__all__' =>
                                    'Semua Status',
                            ],

                            collect(
                                RouteStatus::cases()
                            )
                                ->mapWithKeys(
                                    fn (
                                        RouteStatus $status
                                    ): array => [

                                        $status->value =>
                                            $status
                                                ->getLabel(),
                                    ]
                                )
                                ->all()
                        )
                    )

                    ->query(
                        function (
                            Builder $query,
                            array $data
                        ): Builder {

                            $status =
                                $data[
                                    'value'
                                ]
                                ?? '__active__';

                            if (
                                $status ===
                                '__active__'
                            ) {

                                return
                                    $query->where(
                                        'delivery_routes.status',
                                        '!=',
                                        RouteStatus
                                            ::Cancelled
                                            ->value
                                    );
                            }

                            if (
                                $status ===
                                '__all__'
                            ) {

                            return $query;
                            }

                            if (
                                is_string(
                                    $status
                                )
                            &&
                            $status !== ''
                            ) {

                                return
                                    $query->where(
                                        'delivery_routes.status',
                                        $status
                                    );
                            }

                            return $query;
                        }
                    ),

                SelectFilter::make(
                    'approval_status'
                )
                    ->label('Approval')

                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                    ]),
            ])

            ->defaultSort(
                'id',
                'desc'
            )

            ->recordActions([

                ViewAction::make()

                    ->label('Detail')

                    ->icon(
                        'heroicon-o-eye'
                    ),
            ]);
    }
}