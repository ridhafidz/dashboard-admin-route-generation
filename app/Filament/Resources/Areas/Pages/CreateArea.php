<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Areas\AreaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArea extends CreateRecord
{
    use RedirectsToIndex;
    protected static string $resource = AreaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branchId = app(\App\Services\BranchContext::class)->getId();

        if (! $branchId) {
            throw new \RuntimeException(
                'Silakan pilih cabang aktif terlebih dahulu.'
            );
        }

        $data['branch_id'] = $branchId;

        return $data;
    }
}
