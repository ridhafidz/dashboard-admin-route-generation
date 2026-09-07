<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Concerns\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    use RedirectsToIndex;
    
    protected static string $resource = BranchResource::class;
}
