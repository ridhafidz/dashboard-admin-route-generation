<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                ]),

            TextInput::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->required()
                ->extraInputAttributes([
                    'id' =>
                        'store-longitude',
                ]),

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
        ]);
    }
}