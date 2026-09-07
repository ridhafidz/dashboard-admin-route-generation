<?php

namespace App\Filament\Resources\VehicleTypes\RelationManagers;

use App\Enums\VehicleOwnership;
use App\Enums\VehicleStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plate_number')
                    ->label('Plate Number')
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('status')
                    ->label('Status')
                    ->options(
                        collect(VehicleStatus::cases())
                            ->mapWithKeys(fn ($case) => [
                                $case->value => $case->getLabel(),
                            ])
                    )
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plate_number')
            ->columns([
                TextColumn::make('plate_number')
                    ->label('Plate Number')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Vehicle'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}