<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $table = 'vehicle_types';

    protected $casts = [
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',

        'volume_m3' => 'float',

        'packing_utilization' => 'float',

        'capacity_weight_kg' => 'float',
    ];

    public function getCalculatedVolumeM3Attribute(): float
    {
        if (
            ! $this->length_cm
            || ! $this->width_cm
            || ! $this->height_cm
        ) {
            return 0;
        }

        return (
            (float) $this->length_cm
            *
            (float) $this->width_cm
            *
            (float) $this->height_cm
        ) / 1_000_000;
    }

    public function getUsableVolumeM3Attribute(): float
    {
        $utilization =
            $this->packing_utilization > 0
                ? $this->packing_utilization
                : 1;

        return
            $this->calculated_volume_m3
            *
            $utilization;
    }
}