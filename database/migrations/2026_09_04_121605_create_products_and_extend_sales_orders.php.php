<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Master produk
        |--------------------------------------------------------------------------
        */

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('code')->unique();
            $table->string('name');

            /*
             * Harus sama dengan box_type pada vehicle_types.
             */
            $table->enum('box_type', [
                'cold_storage',
                'dry',
            ]);

            /*
             * Informasi isi kemasan.
             */
            $table->unsignedInteger('units_per_carton');

            /*
             * Berat produk.
             */
            $table->decimal('unit_weight_kg', 12, 4);
            $table->decimal('carton_weight_kg', 12, 4);

            /*
             * Volume produk dalam meter kubik.
             *
             * Volume satu PCS menggunakan presisi lebih tinggi
             * karena nilainya bisa sangat kecil.
             */
            $table->decimal('unit_volume_m3', 12, 8);
            $table->decimal('carton_volume_m3', 12, 6);

            /*
             * Harga tidak wajib.
             */
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('carton_price', 15, 2)->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();

            $table->index(
                ['box_type', 'status'],
                'products_box_type_status_idx'
            );

            $table->index(
                'name',
                'products_name_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Orders menjadi header Sales Order
        |--------------------------------------------------------------------------
        */

        Schema::table('orders', function (Blueprint $table) {
            /*
             * Tanggal pengiriman diletakkan pada header.
             * Seluruh detail produk dalam satu SO memiliki jadwal yang sama.
             */
            $table->date('scheduled_date')
                ->nullable()
                ->after('order_date');

            /*
             * Snapshot alamat dan koordinat.
             *
             * Walaupun master toko berubah, order lama tetap menyimpan
             * alamat tujuan ketika order dibuat.
             */
            $table->text('delivery_address')
                ->nullable()
                ->after('scheduled_date');

            $table->decimal('delivery_latitude', 10, 7)
                ->nullable()
                ->after('delivery_address');

            $table->decimal('delivery_longitude', 10, 7)
                ->nullable()
                ->after('delivery_latitude');

            $table->text('notes')
                ->nullable()
                ->after('delivery_longitude');

            $table->index(
                ['scheduled_date', 'status'],
                'orders_scheduled_date_status_idx'
            );
        });

        /*
         * Migrasikan order lama.
         *
         * - scheduled_date diambil dari tanggal package paling awal.
         * - jika order belum mempunyai package, gunakan order_date.
         * - alamat dan koordinat diambil dari master store.
         */
        DB::statement(<<<'SQL'
            UPDATE orders AS o
            LEFT JOIN (
                SELECT
                    order_id,
                    MIN(scheduled_date) AS scheduled_date
                FROM packages
                GROUP BY order_id
            ) AS p
                ON p.order_id = o.id
            LEFT JOIN stores AS s
                ON s.id = o.store_id
            SET
                o.scheduled_date = COALESCE(
                    p.scheduled_date,
                    o.order_date
                ),
                o.delivery_address = s.address,
                o.delivery_latitude = s.latitude,
                o.delivery_longitude = s.longitude
        SQL);

        /*
        |--------------------------------------------------------------------------
        | Packages menjadi detail produk Sales Order
        |--------------------------------------------------------------------------
        */

        Schema::table('packages', function (Blueprint $table) {
            /*
             * Nullable untuk menjaga package lama tetap valid.
             *
             * Semua sales order baru nantinya wajib memilih produk.
             */
            $table->foreignId('product_id')
                ->nullable()
                ->after('order_id')
                ->constrained('products')
                ->nullOnDelete();

            /*
             * PCS = eceran.
             * CARTON = PAX / karton.
             */
            $table->enum('uom', [
                'pcs',
                'carton',
            ])
                ->default('pcs')
                ->after('quantity');

            /*
             * Snapshot jenis produk.
             *
             * Nilainya disalin dari products.box_type supaya order lama
             * tidak berubah saat master produk diedit.
             */
            $table->enum('box_type', [
                'cold_storage',
                'dry',
            ])
                ->nullable()
                ->after('uom');

            /*
             * Snapshot berat dan volume sesuai satuan yang dipilih.
             */
            $table->decimal('unit_weight_kg', 12, 4)
                ->nullable()
                ->after('box_type');

            $table->decimal('unit_volume_m3', 12, 8)
                ->nullable()
                ->after('unit_weight_kg');

            /*
             * Harga dapat diubah pada setiap Sales Order.
             */
            $table->decimal('unit_price', 15, 2)
                ->nullable()
                ->after('unit_volume_m3');

            $table->decimal('total_price', 15, 2)
                ->nullable()
                ->after('unit_price');

            /*
             * Tingkatkan presisi total berat dan volume.
             */
            $table->decimal('weight_kg', 12, 4)
                ->change();

            $table->decimal('volume_m3', 12, 6)
                ->nullable()
                ->change();

            $table->index(
                ['product_id', 'box_type', 'status'],
                'packages_product_box_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(
                'packages_product_box_status_idx'
            );

            $table->dropForeign([
                'product_id',
            ]);

            $table->dropColumn([
                'product_id',
                'uom',
                'box_type',
                'unit_weight_kg',
                'unit_volume_m3',
                'unit_price',
                'total_price',
            ]);

            $table->decimal('weight_kg', 8, 2)
                ->change();

            $table->decimal('volume_m3', 8, 3)
                ->nullable()
                ->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(
                'orders_scheduled_date_status_idx'
            );

            $table->dropColumn([
                'scheduled_date',
                'delivery_address',
                'delivery_latitude',
                'delivery_longitude',
                'notes',
            ]);
        });

        Schema::dropIfExists('products');
    }
};