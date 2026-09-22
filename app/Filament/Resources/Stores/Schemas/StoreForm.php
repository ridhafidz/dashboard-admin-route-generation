<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Services\AreaAssignmentService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\View;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            Textarea::make('address')
                ->label('Alamat')
                ->required()
                ->columnSpanFull()
                ->extraInputAttributes([
                    'id' =>
                        'store-address',
                ]),

            TextInput::make('latitude')
                ->label('Latitude')
                ->numeric()
                ->required()
                ->extraInputAttributes([
                    'id' =>
                        'store-latitude',
                ])

                ->live(
                    onBlur: true
                )

                ->afterStateUpdated(
                    fn (
                        Get $get,
                        Set $set
                    ) =>
                        self::refreshAreaPreview(
                            $get,
                            $set
                        )
                ),

            TextInput::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->required()
                ->extraInputAttributes([
                    'id' =>
                        'store-longitude',
                ])

                ->live(
                    onBlur: true
                )

                ->afterStateUpdated(
                    fn (
                        Get $get,
                        Set $set
                    ) =>
                        self::refreshAreaPreview(
                            $get,
                            $set
                        )
                ),

                View::make(
                    'filament.schemas.components.location-picker'
                )
                    ->viewData([
                        'addressInputId' =>
                            'store-address',

                        'latitudeInputId' =>
                            'store-latitude',

                        'longitudeInputId' =>
                            'store-longitude',
                    ])
                    ->columnSpanFull(),
    
            Hidden::make('area_id')
                ->dehydrated(),

            Placeholder::make('area_preview')
                ->label('Area (otomatis)')
                ->content(fn (Get $get) => $get('area_preview_text') ?? 'Isi latitude & longitude dulu'),

            Hidden::make('area_preview_text'),

            TimePicker::make('opening_time')
                ->label('Jam Buka')
                ->seconds(false),

            TimePicker::make('closing_time')
                ->label('Jam Tutup')
                ->seconds(false),

        ]);
    }

    protected static function refreshAreaPreview(
        Get $get,
        Set $set
    ): void {

        $latitude =
            $get('latitude');

        $longitude =
            $get('longitude');


        if (
            $latitude === null
            ||
            $longitude === null
            ||
            $latitude === ''
            ||
            $longitude === ''
        ) {

            $set(
                'area_preview_text',
                'Isi latitude & longitude terlebih dahulu.'
            );

            return;
        }

        $preview =
            app(
                AreaAssignmentService::class
            )->preview(
                (float) $latitude,
                (float) $longitude
            );

        if (
            ! $preview['branch']
        ) {

            $set(
                'area_preview_text',
                'Cabang aktif tidak ditemukan.'
            );

            return;
        }

        $status =
            $preview['is_new']
                ? 'Area baru akan dibuat saat disimpan'
                : 'Area existing';

        $set(
            'area_preview_text',

            "{$preview['area_code']} "
            . "({$status}) "
            . "— Cabang "
            . $preview['branch']->name
        );
    }
}