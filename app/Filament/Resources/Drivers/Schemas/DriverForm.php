<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Enums\DriverStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->relationship('user', 'name')
                ->label('Akun Login')
                ->disabled(),

            TextInput::make('name')
                ->required(),

            TextInput::make('phone')
                ->tel(),

            Select::make('status')
                ->options(
                    collect(DriverStatus::cases())
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                )
                ->required(),
        ]);
    }
}