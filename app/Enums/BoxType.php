<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BoxType: string implements HasColor, HasLabel
{
    case ColdStorage = 'cold_storage';
    case Dry = 'dry';

    public function getLabel(): string
    {
        return match ($this) {
            self::ColdStorage => 'Cold Storage',
            self::Dry => 'Dry',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ColdStorage => 'info',
            self::Dry => 'gray',
        };
    }
}