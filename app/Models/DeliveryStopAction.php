<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DeliveryStopAction extends Model
{
    use HasUuid;

    protected $fillable = [
        'delivery_stop_id',
        'source_route_id',
        'target_route_id',
        'branch_id',
        'store_id',
        'customer_code',
        'source_date',
        'original_route_date',
        'new_route_date',
        'action_type',
        'reason',
        'action_status',
        'created_by',
    ];

    protected $casts = [
        'source_date' => 'date',
        'original_route_date' => 'date',
        'new_route_date' => 'date',
    ];
}
