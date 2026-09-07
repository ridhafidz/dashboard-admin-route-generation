<?php

namespace App\Models;

use App\Enums\BoxType;
use App\Enums\ProductStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuid;

    protected $fillable = [
        'code',
        'name',
        'box_type',
        'units_per_carton',
        'unit_weight_kg',
        'carton_weight_kg',
        'unit_volume_m3',
        'carton_volume_m3',
        'unit_price',
        'carton_price',
        'status',
    ];

    protected $casts = [
        'box_type' => BoxType::class,
        'status' => ProductStatus::class,

        'units_per_carton' => 'integer',

        'unit_weight_kg' => 'decimal:4',
        'carton_weight_kg' => 'decimal:4',

        'unit_volume_m3' => 'decimal:8',
        'carton_volume_m3' => 'decimal:6',

        'unit_price' => 'decimal:2',
        'carton_price' => 'decimal:2',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}