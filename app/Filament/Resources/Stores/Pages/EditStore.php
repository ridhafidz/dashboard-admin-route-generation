<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Concerns\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    use RedirectsToIndex;
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
