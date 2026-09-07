<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource =
        OrderResource::class;

    protected ?bool $hasDatabaseTransactions =
        true;
}