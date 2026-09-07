<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class FuelLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'vehicle_id',
        'delivery_route_id',
        'fuel_type',
        'liters',
        'price_per_liter',
        'total_cost',
        'filled_at',
    ];

    protected $casts = [
        'liters' => 'decimal:2',
        'price_per_liter' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'filled_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function deliveryRoute()
    {
        return $this->belongsTo(DeliveryRoute::class);
    }
}