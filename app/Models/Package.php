<?php

namespace App\Models;

use App\Enums\BoxType;
use App\Enums\PackageStatus;
use App\Enums\SalesUnit;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Package extends Model
{
    use HasUuid;

    protected $fillable = [
        'order_id',
        'product_id',

        /*
         * Snapshot nama produk.
         */
        'item',

        'quantity',
        'uom',
        'box_type',

        'unit_weight_kg',
        'unit_volume_m3',

        'unit_price',
        'total_price',

        'tracking_number',

        /*
         * Total berat dan volume baris produk.
         */
        'weight_kg',
        'volume_m3',

        'is_fragile',
        'scheduled_date',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',

        'uom' => SalesUnit::class,
        'box_type' => BoxType::class,

        'unit_weight_kg' => 'decimal:4',
        'unit_volume_m3' => 'decimal:8',

        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',

        'weight_kg' => 'decimal:4',
        'volume_m3' => 'decimal:6',

        'is_fragile' => 'boolean',
        'scheduled_date' => 'date',

        'status' => PackageStatus::class,
    ];

    protected static function booted(): void
    {
        /*
         * Perhitungan selalu dijalankan di backend.
         *
         * Jadi nilai berat dan volume tidak hanya bergantung
         * pada JavaScript atau form browser.
         */
        static::saving(function (Package $package): void {
            $package->applyProductCalculation();
            $package->syncScheduledDateFromOrder();
        });

        static::creating(function (Package $package): void {
            if (blank($package->tracking_number)) {
                $package->tracking_number =
                    $package->generateTrackingNumber();
            }

            if (blank($package->status)) {
                $package->status =
                    PackageStatus::Pending;
            }
        });
    }

    protected function applyProductCalculation(): void
    {
        /*
         * Data lama masih diperbolehkan tanpa product_id.
         *
         * Semua Sales Order baru nantinya akan diwajibkan
         * memilih produk melalui form Filament.
         */
        if (! $this->product_id) {
            return;
        }

        $product = Product::query()->find(
            $this->product_id
        );

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' =>
                    'Produk yang dipilih tidak ditemukan.',
            ]);
        }

        $quantity = (int) $this->quantity;

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' =>
                    'Jumlah produk minimal 1.',
            ]);
        }

        $uom = $this->uom instanceof SalesUnit
            ? $this->uom
            : (
                SalesUnit::tryFrom(
                    (string) $this->uom
                ) ?? SalesUnit::Pcs
            );

        $isCarton =
            $uom === SalesUnit::Carton;

        /*
         * Tentukan berat, volume, dan harga berdasarkan UOM.
         */
        $unitWeight = (float) (
            $isCarton
                ? $product->carton_weight_kg
                : $product->unit_weight_kg
        );

        $unitVolume = (float) (
            $isCarton
                ? $product->carton_volume_m3
                : $product->unit_volume_m3
        );

        $defaultPrice = $isCarton
            ? $product->carton_price
            : $product->unit_price;

        /*
         * Jika produk atau UOM berubah dan harga tidak
         * diedit manual, gunakan harga dari master produk.
         */
        $productOrUomChanged =
            $this->isDirty('product_id')
            || $this->isDirty('uom');

        $priceWasManuallyChanged =
            $this->isDirty('unit_price');

        if (
            $this->unit_price === null
            || (
                $productOrUomChanged
                && ! $priceWasManuallyChanged
            )
        ) {
            $this->unit_price =
                $defaultPrice;
        }

        /*
         * Snapshot data produk.
         */
        $this->item =
            $product->name;

        $this->uom =
            $uom;

        $this->box_type =
            $product->box_type;

        $this->unit_weight_kg =
            $unitWeight;

        $this->unit_volume_m3 =
            $unitVolume;

        /*
         * Total berat dan volume.
         */
        $this->weight_kg = round(
            $quantity * $unitWeight,
            4
        );

        $this->volume_m3 = round(
            $quantity * $unitVolume,
            6
        );

        /*
         * Total harga tetap NULL jika produk tidak memiliki harga.
         */
        $this->total_price =
            $this->unit_price !== null
                ? round(
                    $quantity
                    * (float) $this->unit_price,
                    2
                )
                : null;
    }

    protected function syncScheduledDateFromOrder(): void
    {
        if (! $this->order_id) {
            return;
        }

        $scheduledDate = Order::query()
            ->whereKey($this->order_id)
            ->value('scheduled_date');

        if ($scheduledDate) {
            /*
             * Kolom ini dipertahankan untuk kompatibilitas
             * sementara dengan RouteGenerationService lama.
             */
            $this->scheduled_date =
                $scheduledDate;
        }
    }

    protected function generateTrackingNumber(): string
    {
        /*
         * Random suffix digunakan untuk mencegah nomor ganda
         * ketika dua admin membuat order pada waktu bersamaan.
         */
        do {
            $trackingNumber =
                'TRK-'
                . now()->format('Ymd')
                . '-'
                . Str::upper(
                    Str::random(6)
                );
        } while (
            static::query()
                ->where(
                    'tracking_number',
                    $trackingNumber
                )
                ->exists()
        );

        return $trackingNumber;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function deliveryStops(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryStop::class,
            'delivery_stop_package'
        )->withPivot('uuid');
    }
}