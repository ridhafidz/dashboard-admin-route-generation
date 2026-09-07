<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\PackageStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource =
        OrderResource::class;

    protected ?bool $hasDatabaseTransactions =
        true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(
                    function (Order $record): bool {

                        return ! $record
                            ->packages()
                            ->whereIn(
                                'status',
                                [
                                    PackageStatus::Assigned->value,
                                    PackageStatus::Delivered->value,
                                ]
                            )
                            ->exists();
                    }
                ),
        ];
    }
}