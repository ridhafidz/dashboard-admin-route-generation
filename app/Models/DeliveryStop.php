<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeliveryStop extends Model
{
    use HasUuid;

    protected $fillable = [
        'delivery_route_id',
        'store_id',
        'source_customer_code',
        'source_so_numbers',
        'sequence_order',
        'predicted_arrival_time',
        'predicted_service_start_time',
        'predicted_service_end_time',
        'predicted_waiting_minutes',
        'actual_arrival_time',
        'actual_departure_time',
        'status',
        'proof_of_delivery',
    ];

    protected $casts = [
        'source_so_numbers' => 'array',
        'predicted_arrival_time' => 'datetime',
        'predicted_service_start_time' => 'datetime',
        'predicted_service_end_time' => 'datetime',
        'actual_arrival_time' => 'datetime',
        'actual_departure_time' => 'datetime',
        'predicted_waiting_minutes' => 'integer',
        'status' => \App\Enums\DeliveryStopStatus::class,
    ];

    public function deliveryRoute()
    {
        return $this->belongsTo(DeliveryRoute::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'delivery_stop_package')
            ->withPivot('uuid')
            ->withTimestamps();
    }

    public function deliveryLogs()
    {
        return $this->hasMany(DeliveryLog::class)->orderBy('recorded_at');
    }
}
