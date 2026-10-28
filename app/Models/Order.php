<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'visit_key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getTable()
    {
        return config(
            'services.delivery_source.so_table',
            'so_sda'
        );
    }

    public function getConnectionName()
    {
        $connection = config(
            'services.delivery_source.connection'
        );

        if (
            is_string($connection)
            && trim($connection) !== ''
        ) {
            return trim($connection);
        }

        return parent::getConnectionName();
    }
}