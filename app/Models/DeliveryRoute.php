<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeliveryRoute extends Model
{
    use HasUuid;

    protected $fillable = [
        'branch_id',
        'driver_id',
        'vehicle_id',
        'area_id',
        'route_date',
        'status',
        'predicted_duration_minutes',
        'predicted_cost',
        'predicted_package_count',
        'actual_duration_minutes',
        'actual_cost',
        'actual_package_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'route_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => \App\Enums\RouteStatus::class,
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function deliveryStops()
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sequence_order');
    }

    public function deliveryLogs()
    {
        return $this->hasMany(DeliveryLog::class)->orderBy('recorded_at');
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }
}
