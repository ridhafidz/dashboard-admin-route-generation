<?php 

namespace App\Filament\Resources\Branches\Tables; 

use Filament\Actions\BulkActionGroup; 
use Filament\Actions\DeleteBulkAction; 
use Filament\Actions\EditAction; 
use Filament\Tables\Columns\TextColumn; 
use Filament\Tables\Table; 

class BranchesTable 
{ 
    public static function configure(Table $table): Table 
    { 
        return $table 
        ->columns([ 
            TextColumn::make('cab_id') 
            ->label('ID Cabang') 
            ->searchable() 
            ->sortable(),

            TextColumn::make('region_id')
            ->label('ID Region')
            ->searchable()
            ->sortable(),

            TextColumn::make('name') 
            ->label('Nama Cabang') 
            ->searchable() 
            ->sortable(),

            TextColumn::make('lokasi_cabang') 
            ->label('Lokasi Cabang') 
            ->searchable() 
            ->sortable(),

            TextColumn::make('init_cab') 
            ->label('Inisial Cabang') 
            ->limit(40), 

            TextColumn::make('latitude')
            ->label('Latitude')
            ->limit(40),

            TextColumn::make('longitude')
            ->label('Longitude')
            ->limit(40),

            TextColumn::make('status') 
            ->label('Status') 
            ->badge(), 
        ]) 
        ->filters([ 
            // 
        ]) 
        ->recordActions([ 
            EditAction::make(), 
        ]) 
        ->toolbarActions([ 
            BulkActionGroup::make([ 
                DeleteBulkAction::make(), 
        ]), 
        ]); 
    } 
}