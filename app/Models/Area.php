<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasUuid;

    protected $fillable = ['branch_id', 'code',];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function stores(): HasMany
    {
        return $this->hasMany(
            Store::class,
            'area_id'
        );
    }

    public function deliveryRoutes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }

}
