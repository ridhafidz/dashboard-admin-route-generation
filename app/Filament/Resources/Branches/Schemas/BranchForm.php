<?php

namespace App\Filament\Resources\Branches\Schemas;

use App\Enums\BranchStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('cab_id')
                ->label('CAB ID')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(20),

            TextInput::make('name')
                ->label('Nama Cabang')
                ->required()
                ->maxLength(100),

            TextInput::make('lokasi_cabang')
                ->label('Lokasi Cabang')
                ->placeholder('Contoh: BEKASI')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(50),

            TextInput::make('init_cab')
                ->label('Inisial Cabang')
                ->placeholder('Contoh: BKS')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(10),

            TextInput::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->required(),

            TextInput::make('latitude')
                ->label('Latitude')
                ->required(),

            TextInput::make('region_id')
                ->label('Region ID')
                ->required()
                ->maxLength(20),

            Select::make('status')
                ->label('Status')
                ->options(
                    collect(BranchStatus::cases())
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                )
                ->default(BranchStatus::Active->value)
                ->required(),
        ]);
    }
}