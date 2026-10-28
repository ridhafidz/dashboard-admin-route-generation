<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'code',
        'name',
        'box_type',
        'carton_length_cm',
        'carton_width_cm',
        'carton_height_cm',
        'units_per_carton',
        'units_per_pack',
        'unit_volume_cm3',
        'carton_volume_cm3',
    ];

    protected $casts = [
        'carton_length_cm' => 'float',
        'carton_width_cm' => 'float',
        'carton_height_cm' => 'float',

        'units_per_carton' => 'integer',
        'units_per_pack' => 'integer',

        'unit_volume_cm3' => 'float',
        'carton_volume_cm3' => 'float',
    ];

    public function getCalculatedCartonVolumeCm3Attribute(): float
    {
        if (
            ! $this->carton_length_cm
            || ! $this->carton_width_cm
            || ! $this->carton_height_cm
        ) {
            return 0;
        }

        return
            (float) $this->carton_length_cm
            *
            (float) $this->carton_width_cm
            *
            (float) $this->carton_height_cm;
    }

    public function getCalculatedUnitVolumeCm3Attribute(): float
    {
        if (
            ! $this->units_per_carton
            || $this->units_per_carton <= 0
        ) {
            return 0;
        }

        return
            $this->calculated_carton_volume_cm3
            /
            $this->units_per_carton;
    }

    public function getPackVolumeCm3Attribute(): float
    {
        if (
            ! $this->units_per_pack
            || $this->units_per_pack <= 0
        ) {
            return 0;
        }

        return
            $this->calculated_unit_volume_cm3
            *
            $this->units_per_pack;
    }
}