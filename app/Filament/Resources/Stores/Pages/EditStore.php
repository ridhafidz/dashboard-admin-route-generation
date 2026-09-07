<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Concerns\RedirectsToIndex;
use App\Services\AreaAssignmentService;
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $area = app(AreaAssignmentService::class)->resolve((float) $data['latitude'], (float) $data['longitude']);
        $data['area_id'] = $area->id;
        unset($data['area_preview_text']);
        return $data;
    }
}
