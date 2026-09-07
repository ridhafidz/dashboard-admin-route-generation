<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Packages\PackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePackage extends CreateRecord
{
    use RedirectsToIndex;
    protected static string $resource = PackageResource::class;
}
