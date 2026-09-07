<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DriverStatus: string implements HasColor, HasLabel
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Ready = 'ready';
    case InDelivery = 'in_delivery';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inactive => 'Inactive',
            self::Active => 'Active (Standby)',
            self::Ready => 'Ready',
            self::InDelivery => 'In Delivery',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Inactive => 'gray',
            self::Active => 'info',
            self::Ready => 'warning',
            self::InDelivery => 'success',
        };
    }
}