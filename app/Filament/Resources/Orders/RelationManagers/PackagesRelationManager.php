<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\PackageStatus;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
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
                ->numeric()
                ->required()
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
                ->default(PackageStatus::Pending->value)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tracking_number')
            ->columns([
                TextColumn::make('tracking_number')
                    ->searchable(),
                TextColumn::make('item'),
                TextColumn::make('quantity'),
                TextColumn::make('weight_kg')
                    ->suffix(' kg'),
                IconColumn::make('is_fragile')
                    ->boolean()
                    ->label('Fragile'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}