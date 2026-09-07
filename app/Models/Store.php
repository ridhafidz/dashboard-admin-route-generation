<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class Store extends Model
{
    use HasUuid;

    protected $fillable = [
        'area_id', 'name', 'code', 'address',
        'latitude', 'longitude',
        'opening_time', 'closing_time',
        'service_duration_minutes',
    ];

    protected $casts = [
        // Sengaja 'string' bukan 'datetime:H:i' supaya nilai dikirim
        // ke Python sebagai "08:00", bukan objek Carbon.
        'opening_time'             => 'string',
        'closing_time'             => 'string',
        'service_duration_minutes' => 'integer',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function packages()
    {
        return $this->hasManyThrough(Package::class, Order::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

}
