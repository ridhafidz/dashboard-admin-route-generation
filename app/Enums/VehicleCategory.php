<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleCategory: string implements HasColor, HasLabel
{
    case Engkel = 'engkel';
    case Double = 'double';
    case L300 = 'l300';

    public function getLabel(): string
    {
        return match ($this) {
            self::Engkel => 'Engkel',
            self::Double => 'Double',
            self::L300 => 'L300',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Engkel => 'info',
            self::Double => 'warning',
            self::L300 => 'success',
        };
    }
}