<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | AREA / STORE
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'areas',
            ['branch_id'],
            'idx_areas_branch'
        );

        $this->addIndex(
            'stores',
            ['area_id'],
            'idx_stores_area'
        );


        /*
        |--------------------------------------------------------------------------
        | DRIVERS
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'drivers',
            ['branch_id', 'status'],
            'idx_drivers_branch_status'
        );


        /*
        |--------------------------------------------------------------------------
        | VEHICLES
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'vehicles',
            ['branch_id', 'status'],
            'idx_vehicles_branch_status'
        );

        $this->addIndex(
            'vehicles',
            ['vehicle_type_id'],
            'idx_vehicles_type'
        );


        /*
        |--------------------------------------------------------------------------
        | PRODUCTS
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'products',
            ['status'],
            'idx_products_status'
        );

        $this->addIndex(
            'products',
            ['box_type', 'status'],
            'idx_products_box_status'
        );


        /*
        |--------------------------------------------------------------------------
        | SALES ORDER
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'orders',
            ['scheduled_date', 'status'],
            'idx_orders_schedule_status'
        );

        $this->addIndex(
            'orders',
            ['store_id', 'scheduled_date', 'status'],
            'idx_orders_store_schedule_status'
        );


        /*
        |--------------------------------------------------------------------------
        | PACKAGES
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'packages',
            ['order_id', 'status'],
            'idx_packages_order_status'
        );

        $this->addIndex(
            'packages',
            ['product_id'],
            'idx_packages_product'
        );

        $this->addIndex(
            'packages',
            ['status', 'box_type'],
            'idx_packages_status_box'
        );


        /*
        |--------------------------------------------------------------------------
        | DELIVERY ROUTES
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'delivery_routes',
            ['branch_id', 'route_date', 'status'],
            'idx_routes_branch_date_status'
        );

        $this->addIndex(
            'delivery_routes',
            ['driver_id', 'route_date', 'status'],
            'idx_routes_driver_date_status'
        );

        $this->addIndex(
            'delivery_routes',
            ['vehicle_id', 'route_date', 'status'],
            'idx_routes_vehicle_date_status'
        );


        /*
        |--------------------------------------------------------------------------
        | DELIVERY STOPS
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'delivery_stops',
            ['delivery_route_id', 'sequence_order'],
            'idx_stops_route_sequence'
        );

        $this->addIndex(
            'delivery_stops',
            ['delivery_route_id', 'status'],
            'idx_stops_route_status'
        );

        $this->addIndex(
            'delivery_stops',
            ['store_id'],
            'idx_stops_store'
        );


        /*
        |--------------------------------------------------------------------------
        | STOP PACKAGE
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'delivery_stop_package',
            ['delivery_stop_id', 'package_id'],
            'idx_stop_package'
        );


        /*
        |--------------------------------------------------------------------------
        | DELIVERY LOGS
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'delivery_logs',
            ['delivery_route_id', 'recorded_at'],
            'idx_logs_route_time'
        );

        $this->addIndex(
            'delivery_logs',
            ['delivery_route_id', 'event_type', 'recorded_at'],
            'idx_logs_route_event_time'
        );

        $this->addIndex(
            'delivery_logs',
            ['delivery_route_id', 'delivery_stop_id', 'event_type'],
            'idx_logs_route_stop_event'
        );


        /*
        |--------------------------------------------------------------------------
        | DRIVER ATTENDANCES
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'driver_attendances',
            ['driver_id', 'checkin_at'],
            'idx_attendance_driver_checkin'
        );


        /*
        |--------------------------------------------------------------------------
        | FUEL
        |--------------------------------------------------------------------------
        */

        $this->addIndex(
            'fuel_logs',
            ['vehicle_id'],
            'idx_fuel_logs_vehicle'
        );
    }


    public function down(): void
    {
        /*
         * Sengaja tidak otomatis drop.
         *
         * Migration ini bersifat performance enhancement
         * dan mungkin terdapat index existing yang digunakan
         * modul lain.
         */
    }


    protected function addIndex(
        string $table,
        array $columns,
        string $indexName
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }


        /*
         * Pastikan seluruh kolom tersedia.
         */
        foreach ($columns as $column) {

            if (
                ! Schema::hasColumn(
                    $table,
                    $column
                )
            ) {
                return;
            }
        }


        /*
         * Cek berdasarkan NAMA index.
         */
        $existing =
            DB::selectOne(
                "
                SELECT COUNT(*) AS total
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?
                ",
                [
                    $table,
                    $indexName,
                ]
            );


        if (
            (int) (
                $existing->total
                ?? 0
            ) > 0
        ) {
            return;
        }


        $quotedColumns =
            collect($columns)
                ->map(
                    fn ($column) =>
                        "`{$column}`"
                )
                ->implode(', ');


        DB::statement(
            "CREATE INDEX `{$indexName}` "
            . "ON `{$table}` ({$quotedColumns})"
        );
    }
};