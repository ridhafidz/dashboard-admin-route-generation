<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class Area extends Model
{
    use HasUuid;

    protected $fillable = ['branch_id', 'code'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function deliveryRoutes()
    {
        return $this->hasMany(DeliveryRoute::class);
    }
}
