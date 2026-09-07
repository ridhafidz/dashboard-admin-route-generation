<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PackageStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Assigned => 'Assigned',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Assigned => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
        };
    }
}