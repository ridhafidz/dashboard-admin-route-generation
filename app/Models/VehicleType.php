<?php

namespace App\Models;

use App\Enums\BoxType;
use App\Enums\VehicleCategory;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasUuid;

    protected $fillable = [
        'category',
        'box_type',
        'length_cm',
        'width_cm',
        'height_cm',
    ];

    protected $casts = [
        'category' => VehicleCategory::class,
        'box_type' => BoxType::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (VehicleType $vehicleType) {
            if ($vehicleType->length_cm && $vehicleType->width_cm && $vehicleType->height_cm) {
                $vehicleType->volume_m3 = round(
                    ($vehicleType->length_cm * $vehicleType->width_cm * $vehicleType->height_cm) / 1_000_000,
                    3
                );
            }
        });
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
}
