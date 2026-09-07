<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Enums\PackageStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('order_id')
                ->relationship('order', 'order_number')
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('item')
                ->required(),
            TextInput::make('quantity')
                ->numeric()
                ->default(1)
                ->required(),
            TextInput::make('tracking_number')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('weight_kg')
                ->numeric()->required()
                ->suffix('kg'),
            TextInput::make('volume_m3')
                ->numeric()
                ->suffix('m³'),
            Toggle::make('is_fragile')
                ->label('Mudah Pecah'),
            DatePicker::make('scheduled_date')
                ->required(),

            Select::make('status')
                ->options(collect(PackageStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))
                ->required(),
        ]);
    }
}