<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Stores\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    use RedirectsToIndex;
    protected static string $resource = StoreResource::class;
}
