<?php

namespace App\Filament\Resources\VehicleTypes\Pages;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\VehicleTypes\VehicleTypeResource;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleType extends EditRecord
{
    use RedirectsToIndex;
    protected static string $resource = VehicleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
