<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalesUnit: string implements HasLabel
{
    case Pcs = 'pcs';
    case Carton = 'carton';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pcs => 'PCS / Eceran',
            self::Carton => 'PAX / Karton',
        };
    }
}