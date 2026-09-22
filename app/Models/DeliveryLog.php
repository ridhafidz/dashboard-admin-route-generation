<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class DeliveryLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'delivery_route_id',
        'delivery_stop_id',
        'latitude',
        'longitude',
        'event_type',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' =>
            'decimal:7',

        'longitude' =>
            'decimal:7',

        'recorded_at' =>
            'datetime',
    ];

    public function deliveryRoute()
    {
        return $this->belongsTo(DeliveryRoute::class);
    }

    public function deliveryStop()
    {
        return $this->belongsTo(DeliveryStop::class);
    }
}