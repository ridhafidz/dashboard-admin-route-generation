<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDeferredDemand extends Model
{
    protected $fillable = [
        'branch_id',
        'demand_id',
        'source_date',
        'original_route_date',
        'target_route_date',

        'customer_code',
        'customer_name',

        'box_type',

        'source_so_numbers',
        'demand_payload',

        'reason',

        'required_volume_cm3',
        'max_compatible_capacity_cm3',
        'largest_remaining_volume_cm3',

        'status',
        'resolved_at',
    ];

    protected $casts = [
        'source_date' => 'date',
        'original_route_date' => 'date',
        'target_route_date' => 'date',

        'source_so_numbers' => 'array',
        'demand_payload' => 'array',

        'required_volume_cm3' => 'float',
        'max_compatible_capacity_cm3' => 'float',
        'largest_remaining_volume_cm3' => 'float',

        'resolved_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(
            Branch::class
        );
    }
}