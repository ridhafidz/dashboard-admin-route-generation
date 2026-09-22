<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. DELIVERY ROUTE SUMMARY
        |--------------------------------------------------------------------------
        |
        | Untuk:
        | - List Delivery Routes
        | - Dashboard / report
        | - Mengurangi repetitive JOIN
        |
        */

        DB::statement('DROP VIEW IF EXISTS vw_delivery_route_summary');

        DB::statement("
            CREATE VIEW vw_delivery_route_summary AS

            SELECT
                dr.id,
                dr.uuid,

                dr.branch_id,
                b.init_cab AS branch_initial,
                b.name AS branch_name,

                dr.driver_id,
                d.name AS driver_name,
                d.status AS driver_status,

                dr.vehicle_id,
                v.plate_number,
                v.vehicle_type_id,
                v.status AS vehicle_status,

                dr.area_id,
                dr.route_date,
                dr.status,

                dr.predicted_duration_minutes,
                dr.predicted_cost,
                dr.predicted_package_count,

                dr.actual_duration_minutes,
                dr.actual_cost,
                dr.actual_package_count,

                dr.started_at,
                dr.completed_at,

                COUNT(ds.id) AS total_stops,

                SUM(
                    CASE
                        WHEN ds.status = 'pending' THEN 1
                        ELSE 0
                    END
                ) AS pending_stops,

                SUM(
                    CASE
                        WHEN ds.status = 'arrived' THEN 1
                        ELSE 0
                    END
                ) AS arrived_stops,

                SUM(
                    CASE
                        WHEN ds.status = 'completed' THEN 1
                        ELSE 0
                    END
                ) AS completed_stops,

                SUM(
                    CASE
                        WHEN ds.status = 'skipped' THEN 1
                        ELSE 0
                    END
                ) AS skipped_stops,

                MIN(ds.predicted_arrival_time)
                    AS first_predicted_arrival_time,

                MAX(ds.predicted_service_end_time)
                    AS last_predicted_service_end_time,

                dr.created_at,
                dr.updated_at

            FROM delivery_routes dr

            LEFT JOIN branches b
                ON b.id = dr.branch_id

            LEFT JOIN drivers d
                ON d.id = dr.driver_id

            LEFT JOIN vehicles v
                ON v.id = dr.vehicle_id

            LEFT JOIN delivery_stops ds
                ON ds.delivery_route_id = dr.id

            GROUP BY
                dr.id,
                dr.uuid,

                dr.branch_id,
                b.init_cab,
                b.name,

                dr.driver_id,
                d.name,
                d.status,

                dr.vehicle_id,
                v.plate_number,
                v.vehicle_type_id,
                v.status,

                dr.area_id,
                dr.route_date,
                dr.status,

                dr.predicted_duration_minutes,
                dr.predicted_cost,
                dr.predicted_package_count,

                dr.actual_duration_minutes,
                dr.actual_cost,
                dr.actual_package_count,

                dr.started_at,
                dr.completed_at,

                dr.created_at,
                dr.updated_at
        ");


        /*
        |--------------------------------------------------------------------------
        | 2. SALES ORDER SUMMARY
        |--------------------------------------------------------------------------
        |
        | orders = header Sales Order
        | packages = item/package order
        |
        | Untuk:
        | - List Sales Order
        | - Dashboard Sales Order
        | - Filter & reporting
        |
        */

        DB::statement('DROP VIEW IF EXISTS vw_sales_order_summary');

        DB::statement("
            CREATE VIEW vw_sales_order_summary AS

            SELECT
                o.id,
                o.uuid,
                o.order_number,

                o.store_id,
                s.uuid AS store_uuid,
                s.code AS store_code,
                s.name AS store_name,
                s.area_id AS store_area_id,

                o.order_date,
                o.scheduled_date,

                o.delivery_address,
                o.delivery_latitude,
                o.delivery_longitude,

                o.notes,
                o.status,

                COUNT(p.id) AS package_count,

                COALESCE(
                    SUM(p.quantity),
                    0
                ) AS total_quantity,

                COALESCE(
                    SUM(p.weight_kg),
                    0
                ) AS total_weight_kg,

                COALESCE(
                    SUM(p.volume_m3),
                    0
                ) AS total_volume_m3,

                COALESCE(
                    SUM(p.total_price),
                    0
                ) AS total_order_value,

                SUM(
                    CASE
                        WHEN p.status = 'pending' THEN 1
                        ELSE 0
                    END
                ) AS pending_packages,

                SUM(
                    CASE
                        WHEN p.status = 'assigned' THEN 1
                        ELSE 0
                    END
                ) AS assigned_packages,

                SUM(
                    CASE
                        WHEN p.status = 'delivered' THEN 1
                        ELSE 0
                    END
                ) AS delivered_packages,

                SUM(
                    CASE
                        WHEN p.status = 'failed' THEN 1
                        ELSE 0
                    END
                ) AS failed_packages,

                SUM(
                    CASE
                        WHEN p.box_type = 'cold_storage' THEN 1
                        ELSE 0
                    END
                ) AS cold_storage_packages,

                SUM(
                    CASE
                        WHEN p.box_type = 'dry' THEN 1
                        ELSE 0
                    END
                ) AS dry_packages,

                o.created_at,
                o.updated_at

            FROM orders o

            INNER JOIN stores s
                ON s.id = o.store_id

            LEFT JOIN packages p
                ON p.order_id = o.id

            GROUP BY
                o.id,
                o.uuid,
                o.order_number,

                o.store_id,
                s.uuid,
                s.code,
                s.name,
                s.area_id,

                o.order_date,
                o.scheduled_date,

                o.delivery_address,
                o.delivery_latitude,
                o.delivery_longitude,

                o.notes,
                o.status,

                o.created_at,
                o.updated_at
        ");


        /*
        |--------------------------------------------------------------------------
        | 3. CURRENT DRIVER TRACKING
        |--------------------------------------------------------------------------
        |
        | Mengambil GPS terakhir berdasarkan ID delivery_log terbesar.
        |
        | Tujuan utama:
        | DriverTracking Livewire tidak perlu load seluruh delivery_logs.
        |
        */

        DB::statement('DROP VIEW IF EXISTS vw_driver_tracking_current');

        DB::statement("
            CREATE VIEW vw_driver_tracking_current AS

            SELECT
                dr.id AS delivery_route_id,
                dr.uuid AS delivery_route_uuid,

                dr.route_date,
                dr.status AS route_status,

                dr.branch_id,
                b.init_cab AS branch_initial,
                b.name AS branch_name,

                dr.driver_id,
                d.uuid AS driver_uuid,
                d.name AS driver_name,
                d.status AS driver_status,

                dr.vehicle_id,
                v.uuid AS vehicle_uuid,
                v.plate_number,
                v.status AS vehicle_status,

                lg.id AS latest_log_id,
                lg.uuid AS latest_log_uuid,

                lg.latitude AS current_latitude,
                lg.longitude AS current_longitude,

                lg.event_type AS latest_event_type,
                lg.recorded_at AS last_gps_at,

                dr.started_at,
                dr.completed_at

            FROM delivery_routes dr

            INNER JOIN drivers d
                ON d.id = dr.driver_id

            INNER JOIN vehicles v
                ON v.id = dr.vehicle_id

            LEFT JOIN branches b
                ON b.id = dr.branch_id

            LEFT JOIN delivery_logs lg
                ON lg.id = (
                    SELECT MAX(dl.id)

                    FROM delivery_logs dl

                    WHERE dl.delivery_route_id = dr.id
                      AND dl.event_type = 'gps_ping'
                )
        ");
    }


    public function down(): void
    {
        DB::statement(
            'DROP VIEW IF EXISTS vw_driver_tracking_current'
        );

        DB::statement(
            'DROP VIEW IF EXISTS vw_sales_order_summary'
        );

        DB::statement(
            'DROP VIEW IF EXISTS vw_delivery_route_summary'
        );
    }
};