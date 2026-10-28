<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $table = 'vehicle_types';

    protected $fillable = [
        'category',
        'box_type',
        'length_cm',
        'width_cm',
        'height_cm',
    ];

    protected $casts = [
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
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
        $volumeM3 =
            (float) (
                $this->volume_m3
                ?? 0
            );

        if ($volumeM3 <= 0) {
            $volumeM3 =
                $this->calculated_volume_m3;
        }

        $utilization =
            $this->packing_utilization > 0
                ? (float) $this->packing_utilization
                : 1;

        return
            $volumeM3
            *
            $utilization;
    }
}