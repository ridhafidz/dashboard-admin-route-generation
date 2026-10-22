<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_delivery_route_summary');

        DB::statement("
            CREATE VIEW vw_delivery_route_summary AS

            SELECT
                dr.id,
                dr.uuid,

                dr.branch_id,
                b.init_cab AS branch_initial,
                b.name AS branch_name,
                b.status AS branch_status,

                dr.driver_id,
                d.uuid AS driver_uuid,
                d.name AS driver_name,
                d.status AS driver_status,

                dr.vehicle_id,
                v.uuid AS vehicle_uuid,
                v.plate_number,
                v.status AS vehicle_status,
                v.vehicle_type_id,

                vt.category AS vehicle_category,
                vt.box_type AS vehicle_box_type,

                dr.source_date,
                dr.route_date,
                dr.status,
                dr.approval_status,
                dr.approved_at,
                dr.approved_by,

                dr.predicted_duration_minutes,
                dr.predicted_cost,
                dr.predicted_package_count,

                dr.actual_duration_minutes,
                dr.actual_cost,
                dr.actual_package_count,

                dr.started_at,
                dr.completed_at,

                COALESCE(ds.total_stops, 0) AS total_stops,
                COALESCE(ds.pending_stops, 0) AS pending_stops,
                COALESCE(ds.arrived_stops, 0) AS arrived_stops,
                COALESCE(ds.completed_stops, 0) AS completed_stops,
                COALESCE(ds.skipped_stops, 0) AS skipped_stops,

                ds.first_predicted_arrival,
                ds.last_predicted_service_end,

                dr.created_at,
                dr.updated_at

            FROM delivery_routes dr

            LEFT JOIN branches b
                ON b.id = dr.branch_id

            LEFT JOIN drivers d
                ON d.id = dr.driver_id

            LEFT JOIN vehicles v
                ON v.id = dr.vehicle_id

            LEFT JOIN vehicle_types vt
                ON vt.id = v.vehicle_type_id

            LEFT JOIN (
                SELECT
                    delivery_route_id,
                    COUNT(*) AS total_stops,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_stops,
                    SUM(CASE WHEN status = 'arrived' THEN 1 ELSE 0 END) AS arrived_stops,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_stops,
                    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) AS skipped_stops,
                    MIN(predicted_arrival_time) AS first_predicted_arrival,
                    MAX(predicted_service_end_time) AS last_predicted_service_end
                FROM delivery_stops
                GROUP BY delivery_route_id
            ) ds
                ON ds.delivery_route_id = dr.id
        ");

        /*
        |--------------------------------------------------------------------------
        | 2. CURRENT DRIVER TRACKING
        |--------------------------------------------------------------------------
        |
        | Mengambil GPS terakhir berdasarkan ID delivery_log terbesar.
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
        DB::statement('DROP VIEW IF EXISTS vw_driver_tracking_current');
        DB::statement('DROP VIEW IF EXISTS vw_delivery_route_summary');
    }
};