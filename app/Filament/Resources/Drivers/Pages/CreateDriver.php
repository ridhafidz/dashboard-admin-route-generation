<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Drivers\DriverResource;
use App\Services\BranchContext;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branchId = app(BranchContext::class)->getId();

        if (!$branchId) {
            throw new \RuntimeException(
                'Silakan pilih cabang aktif terlebih dahulu.'
            );
        }

        $data['branch_id'] = $branchId;

        return $data;
    }
}