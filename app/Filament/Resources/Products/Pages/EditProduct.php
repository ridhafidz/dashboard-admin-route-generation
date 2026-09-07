<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource =
        ProductResource::class;
}