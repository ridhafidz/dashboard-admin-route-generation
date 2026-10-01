<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;

/**
 * CustomerSource
 *
 * Proxy Eloquent READ ONLY ke master customer source.
 *
 * PREP INTEGRASI SISTEM UTAMA:
 * - sekarang : default connection + cust_sda
 * - nanti    : main_system + tabel customer database utama
 * - credential tetap di .env / config/database.php
 *
 * Field bisnis yang dipakai aplikasi:
 * customerID, customerName, addressLine1, city, phone, taxName,
 * latitude, longitude, area, subArea.
 * cabID hanya dipakai sebagai mapping/filter cabang.
 *
 * @property string      $customerID
 * @property string|null $customerName
 * @property string|null $addressLine1
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $taxName
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $area
 * @property string|null $subArea
 * @property string|null $cabID
 */
class CustomerSource extends Model
{
    /*
    |--------------------------------------------------------------------------
    | PREP INTEGRASI SISTEM UTAMA
    |--------------------------------------------------------------------------
    |
    | Saat database utama tersedia:
    | protected $connection = 'main_system';
    | protected $table = 'customers'; // sesuaikan nama aktual
    |
    */
    // protected $connection = 'main_system';

    protected $table = 'cust_sda';

    protected $primaryKey = 'customerID';

    public $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];

    protected $guarded = ['*'];

    /**
     * Batasi attribute yang diekspos dari model source.
     * Ini bukan pengganti SELECT whitelist; ListStores tetap melakukan SELECT
     * kolom eksplisit agar database tidak mengirim seluruh kolom cust_sda.
     */
    protected $visible = [
        'customerID',
        'customerName',
        'addressLine1',
        'city',
        'phone',
        'taxName',
        'latitude',
        'longitude',
        'area',
        'subArea',
        'cabID',
    ];

    /**
     * Source customer bersifat read-only. Guard ini mencegah write tidak sengaja
     * bila model proxy dipakai di kode lain pada masa depan.
     */
    protected static function booted(): void
    {
        static::creating(function (): never {
            throw new LogicException('CustomerSource bersifat read-only.');
        });

        static::updating(function (): never {
            throw new LogicException('CustomerSource bersifat read-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('CustomerSource bersifat read-only.');
        });
    }
}
