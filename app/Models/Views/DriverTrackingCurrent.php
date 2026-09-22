<?php

namespace App\Models\Views;

use Illuminate\Database\Eloquent\Model;

class DriverTrackingCurrent extends Model
{
    protected $table = 'vw_driver_tracking_current';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'delivery_route_id' => 'integer',
        'branch_id' => 'integer',
        'driver_id' => 'integer',
        'vehicle_id' => 'integer',
        'latest_log_id' => 'integer',

        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',

        'route_date' => 'date',
        'last_gps_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}