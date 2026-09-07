<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class FuelPrice extends Model
{
    use HasUuid;

    protected $fillable = ['fuel_type', 'price_per_liter', 'effective_date'];

    protected $casts = [
        'effective_date' => 'date',
    ];
}