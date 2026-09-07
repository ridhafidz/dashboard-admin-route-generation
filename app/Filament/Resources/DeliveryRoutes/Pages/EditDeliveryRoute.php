<?php

namespace App\Filament\Resources\DeliveryRoutes\Pages;

use App\Filament\Resources\DeliveryRoutes\DeliveryRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryRoute extends EditRecord
{
    protected static string $resource = DeliveryRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
