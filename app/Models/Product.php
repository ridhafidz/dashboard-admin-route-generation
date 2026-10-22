<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $casts = [
        'carton_length_cm' => 'float',
        'carton_width_cm' => 'float',
        'carton_height_cm' => 'float',

        'units_per_carton' => 'integer',
        'units_per_pack' => 'integer',

        'unit_volume_cm3' => 'float',
        'carton_volume_cm3' => 'float',
    ];

    public function getCalculatedCartonVolumeM3Attribute(): float
    {
        if (
            ! $this->carton_length_cm
            || ! $this->carton_width_cm
            || ! $this->carton_height_cm
        ) {
            return 0;
        }

        return (
            (float) $this->carton_length_cm
            *
            (float) $this->carton_width_cm
            *
            (float) $this->carton_height_cm
        ) / 1_000_000;
    }

    public function getCalculatedUnitVolumeM3Attribute(): float
    {
        if (
            ! $this->units_per_carton
            || $this->units_per_carton <= 0
        ) {
            return 0;
        }

        return
            $this->calculated_carton_volume_m3
            /
            $this->units_per_carton;
    }

    public function getPackVolumeM3Attribute(): float
    {
        if (
            ! $this->units_per_pack
            || $this->units_per_pack <= 0
        ) {
            return 0;
        }

        return
            $this->calculated_unit_volume_m3
            *
            $this->units_per_pack;
    }
}