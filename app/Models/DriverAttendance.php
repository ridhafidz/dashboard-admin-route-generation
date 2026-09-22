<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DriverAttendance extends Model
{
    use HasUuid;

    protected $fillable = [
        'driver_id',

        'checkin_at',
        'checkin_latitude',
        'checkin_longitude',
        'checkin_photo',

        'checkout_at',
        'checkout_latitude',
        'checkout_longitude',
        'checkout_photo',
    ];

    protected $casts = [
        'checkin_at' =>
            'datetime',

        'checkout_at' =>
            'datetime',

        'checkin_latitude' =>
            'decimal:7',

        'checkin_longitude' =>
            'decimal:7',

        'checkout_latitude' =>
            'decimal:7',

        'checkout_longitude' =>
            'decimal:7',
    ];

    public function driver()
    {
        return $this->belongsTo(
            Driver::class
        );
    }
}