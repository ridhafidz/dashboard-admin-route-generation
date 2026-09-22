<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\AreaAssignmentService;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasUuid;


    protected $fillable = [
        'area_id',
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
        'service_duration_minutes',
    ];


    protected $casts = [

        /*
         * Sengaja string supaya nilai
         * dikirim ke Python sebagai "08:00".
         */
        'opening_time' =>
            'string',

        'closing_time' =>
            'string',

        'service_duration_minutes' =>
            'integer',
    ];


    /*
    |--------------------------------------------------------------------------
    | AUTO AREA ASSIGNMENT
    |--------------------------------------------------------------------------
    |
    | Area dihitung ulang hanya ketika:
    |
    | - Store baru
    | - latitude berubah
    | - longitude berubah
    | - area_id belum ada
    |
    | Jadi perubahan nama/alamat biasa tidak membuat Area baru.
    |
    */

    protected static function booted(): void
    {
        static::saving(
            function (
                Store $store
            ): void {

                /*
                 * Kalau Store existing dan koordinat
                 * tidak berubah, Area jangan dihitung ulang.
                 */
                if (
                    $store->exists
                    &&
                    ! $store->isDirty(
                        [
                            'latitude',
                            'longitude',
                        ]
                    )
                    &&
                    $store->area_id !== null
                ) {
                    return;
                }


                if (
                    $store->latitude === null
                    ||
                    $store->longitude === null
                    ||
                    $store->latitude === ''
                    ||
                    $store->longitude === ''
                ) {
                    return;
                }


                /*
                 * Gunakan rule Area yang SAMA
                 * dengan preview form.
                 *
                 * Kalau ada Area dalam radius:
                 * → gunakan Area existing.
                 *
                 * Kalau tidak ada:
                 * → buat Area baru.
                 */
                $area =
                    app(
                        AreaAssignmentService::class
                    )->resolve(
                        (float) $store->latitude,
                        (float) $store->longitude
                    );


                /*
                 * Paksa area_id hasil kalkulasi
                 * mengalahkan area_id lama dari form.
                 */
                $store->area_id =
                    $area->id;
            }
        );
    }


    public function area()
    {
        return
            $this->belongsTo(
                Area::class
            );
    }


    public function packages()
    {
        return
            $this->hasManyThrough(
                Package::class,
                Order::class
            );
    }


    public function orders()
    {
        return
            $this->hasMany(
                Order::class
            );
    }
}