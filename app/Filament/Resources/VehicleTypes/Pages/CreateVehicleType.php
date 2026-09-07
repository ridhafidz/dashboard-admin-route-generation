<?php

namespace App\Filament\Resources\VehicleTypes\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\VehicleTypes\VehicleTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleType extends CreateRecord
{
    use RedirectsToIndex;
    protected static string $resource = VehicleTypeResource::class;
}
