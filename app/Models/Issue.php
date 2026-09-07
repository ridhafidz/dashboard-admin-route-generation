<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class Issue extends Model
{
    use HasUuid;

    protected $fillable = [
        'reported_by',
        'delivery_route_id',
        'category',
        'title',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => \App\Enums\IssueStatus::class,
    ];

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function deliveryRoute()
    {
        return $this->belongsTo(DeliveryRoute::class);
    }
}
