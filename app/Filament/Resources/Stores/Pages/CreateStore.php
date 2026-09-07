<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Stores\StoreResource;
use App\Services\AreaAssignmentService;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    use RedirectsToIndex;
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $area = app(AreaAssignmentService::class)->resolve((float) $data['latitude'], (float) $data['longitude']);
        $data['area_id'] = $area->id;
        unset($data['area_preview_text']); // field ini cuma buat UI, jangan sampe ikut ke database
        return $data;
    }
}
