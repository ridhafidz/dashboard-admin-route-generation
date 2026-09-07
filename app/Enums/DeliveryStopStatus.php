<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryStopStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Arrived => 'Arrived',
            self::Completed => 'Completed',
            self::Skipped => 'Skipped',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Arrived => 'info',
            self::Completed => 'success',
            self::Skipped => 'danger',
        };
    }
}