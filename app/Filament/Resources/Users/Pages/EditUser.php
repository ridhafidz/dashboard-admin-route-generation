<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Users\UserResource;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use RedirectsToIndex;
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
