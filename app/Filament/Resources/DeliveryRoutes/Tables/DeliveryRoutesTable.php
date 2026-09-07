<?php

namespace App\Filament\Resources\DeliveryRoutes\Tables;

use App\Enums\RouteStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeliveryRoutesTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([

                TextColumn::make('id')
                    ->label('Route')
                    ->formatStateUsing(
                        fn ($state): string =>
                            '#' . $state
                    )
                    ->sortable(),

                TextColumn::make('route_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make(
                    'branch.init_cab'
                )
                    ->label('Cabang')
                    ->description(
                        fn ($record): ?string =>
                            $record
                                ->branch
                                ?->name
                    )
                    ->searchable(),

                TextColumn::make(
                    'driver.name'
                )
                    ->label('Driver')
                    ->searchable(),

                TextColumn::make(
                    'vehicle.plate_number'
                )
                    ->label('Vehicle')
                    ->description(
                        function ($record): ?string {

                            $vehicleType =
                                $record
                                    ->vehicle
                                    ?->vehicleType;

                            if (! $vehicleType) {
                                return null;
                            }

                            $category =
                                $vehicleType
                                    ->category
                                    ?->getLabel()
                                ?? '-';

                            $box =
                                $vehicleType
                                    ->box_type
                                    ?->getLabel()
                                ?? '-';

                            return
                                $category
                                . ' - '
                                . $box;
                        }
                    )
                    ->searchable(),

                TextColumn::make(
                    'delivery_stops_count'
                )
                    ->counts(
                        'deliveryStops'
                    )
                    ->label('Stop')
                    ->badge(),

                TextColumn::make(
                    'predicted_package_count'
                )
                    ->label('Package')
                    ->numeric(
                        decimalPlaces: 0
                    )
                    ->badge(),

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

                TextColumn::make('status')
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
                            RouteStatus::cases()
                        )->mapWithKeys(
                            fn (
                                RouteStatus $status
                            ) => [
                                $status->value =>
                                    $status
                                        ->getLabel(),
                            ]
                        )
                    ),
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