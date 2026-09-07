<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
use App\Models\Branch;
use App\Models\VehicleType;
use App\Models\FuelLog;
use App\Models\DeliveryRoute;
use App\Enums\VehicleStatus;

class Vehicle extends Model
{
    use HasUuid;

    protected $fillable = [
        'vehicle_type_id',
        'branch_id',
        'plate_number',
        'status',
    ];

    protected $casts = [
        'status' => \App\Enums\VehicleStatus::class,
    ];

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function deliveryRoutes()
    {
        return $this->hasMany(DeliveryRoute::class);
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}