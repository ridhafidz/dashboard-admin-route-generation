<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Enums\BranchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasUuid;

    protected $fillable = [
        'cab_id',
        'name',
        'lokasi_cabang',
        'longitude',
        'latitude',
        'region_id',
        'init_cab',
        'status',
        'start_time',
    ];

    protected $casts = [
        'status'     => BranchStatus::class,
        'start_time' => 'string',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function deliveryRoutes(): HasMany
    {
        return $this->hasMany(DeliveryRoute::class);
    }
}   
