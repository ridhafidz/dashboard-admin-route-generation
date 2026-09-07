<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource =
        ProductResource::class;
}