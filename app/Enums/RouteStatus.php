<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RouteStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Ongoing => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}