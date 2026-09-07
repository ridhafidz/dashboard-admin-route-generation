<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuid;

    protected $fillable = [
        'store_id',
        'order_number',
        'order_date',
        'scheduled_date',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'notes',
        'status',
    ];

    protected $casts = [
        'order_date' => 'date',
        'scheduled_date' => 'date',

        'delivery_latitude' => 'decimal:7',
        'delivery_longitude' => 'decimal:7',

        'status' => OrderStatus::class,
    ];

    protected static function booted(): void
    {
        /*
         * Saat order dibuat, alamat dan koordinat toko disalin
         * menjadi snapshot tujuan pengiriman.
         */
        static::creating(function (Order $order): void {
            $order->copyDestinationFromStore();

            if (blank($order->scheduled_date)) {
                $order->scheduled_date =
                    $order->order_date ?? today();
            }
        });

        /*
         * Snapshot tujuan hanya diperbarui apabila toko diganti.
         *
         * Perubahan alamat pada master toko tidak mengubah
         * histori order lama secara otomatis.
         */
        static::updating(function (Order $order): void {
            if ($order->isDirty('store_id')) {
                $order->copyDestinationFromStore();
            }
        });
    }

    protected function copyDestinationFromStore(): void
    {
        if (! $this->store_id) {
            return;
        }

        $store = Store::query()->find(
            $this->store_id
        );

        if (! $store) {
            return;
        }

        $this->delivery_address =
            $store->address;

        $this->delivery_latitude =
            $store->latitude;

        $this->delivery_longitude =
            $store->longitude;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(
            Store::class
        );
    }

    public function packages(): HasMany
    {
        return $this->hasMany(
            Package::class
        );
    }
}