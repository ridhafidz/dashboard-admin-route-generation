<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
use App\Models\User;

class Driver extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id',
        'branch_id',
        'name',
        'phone',
        'status',
    ];

    protected $casts = [
        'status' => \App\Enums\DriverStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveryRoutes()
    {
        return $this->hasMany(DeliveryRoute::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendances()
    {
        return $this->hasMany(DriverAttendance::class);
    }
}
