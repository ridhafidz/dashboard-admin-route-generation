<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasUuid;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        /*
         * Sengaja string supaya nilai
         * dikirim ke Python sebagai desimal.
         */
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}