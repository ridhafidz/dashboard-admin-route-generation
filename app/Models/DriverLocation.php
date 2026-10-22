<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    protected $table =
        'driver_locations';

    public $timestamps =
        false;

    protected $fillable = [
        'driver_id',
        'delivery_route_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' =>
            'float',

        'longitude' =>
            'float',

        'accuracy' =>
            'float',

        'speed' =>
            'float',

        'heading' =>
            'float',

        'recorded_at' =>
            'datetime',
    ];
}