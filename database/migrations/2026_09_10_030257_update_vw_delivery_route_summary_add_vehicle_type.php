<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW vw_delivery_route_summary AS

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

                /*
                |--------------------------------------------------------------------------
                | VEHICLE TYPE
                |--------------------------------------------------------------------------
                */

                vt.category AS vehicle_category,
                vt.box_type AS vehicle_box_type,

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

                COALESCE(ds.total_stops, 0)
                    AS total_stops,

                COALESCE(ds.pending_stops, 0)
                    AS pending_stops,

                COALESCE(ds.arrived_stops, 0)
                    AS arrived_stops,

                COALESCE(ds.completed_stops, 0)
                    AS completed_stops,

                COALESCE(ds.skipped_stops, 0)
                    AS skipped_stops,

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

            /*
            |--------------------------------------------------------------------------
            | VEHICLE TYPE
            |--------------------------------------------------------------------------
            */

            LEFT JOIN vehicle_types vt
                ON vt.id = v.vehicle_type_id

            /*
            |--------------------------------------------------------------------------
            | DELIVERY STOP SUMMARY
            |--------------------------------------------------------------------------
            |
            | Aggregate dilakukan satu kali per route.
            |
            */

            LEFT JOIN (
                SELECT
                    delivery_route_id,

                    COUNT(*) AS total_stops,

                    SUM(
                        CASE
                            WHEN status = 'pending'
                            THEN 1
                            ELSE 0
                        END
                    ) AS pending_stops,

                    SUM(
                        CASE
                            WHEN status = 'arrived'
                            THEN 1
                            ELSE 0
                        END
                    ) AS arrived_stops,

                    SUM(
                        CASE
                            WHEN status = 'completed'
                            THEN 1
                            ELSE 0
                        END
                    ) AS completed_stops,

                    SUM(
                        CASE
                            WHEN status = 'skipped'
                            THEN 1
                            ELSE 0
                        END
                    ) AS skipped_stops,

                    MIN(
                        predicted_arrival_time
                    ) AS first_predicted_arrival,

                    MAX(
                        predicted_service_end_time
                    ) AS last_predicted_service_end

                FROM delivery_stops

                GROUP BY
                    delivery_route_id

            ) ds
                ON ds.delivery_route_id = dr.id
        ");
    }


    public function down(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW vw_delivery_route_summary AS

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

                COALESCE(ds.total_stops, 0)
                    AS total_stops,

                COALESCE(ds.pending_stops, 0)
                    AS pending_stops,

                COALESCE(ds.arrived_stops, 0)
                    AS arrived_stops,

                COALESCE(ds.completed_stops, 0)
                    AS completed_stops,

                COALESCE(ds.skipped_stops, 0)
                    AS skipped_stops,

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

            LEFT JOIN (
                SELECT
                    delivery_route_id,

                    COUNT(*) AS total_stops,

                    SUM(
                        CASE
                            WHEN status = 'pending'
                            THEN 1
                            ELSE 0
                        END
                    ) AS pending_stops,

                    SUM(
                        CASE
                            WHEN status = 'arrived'
                            THEN 1
                            ELSE 0
                        END
                    ) AS arrived_stops,

                    SUM(
                        CASE
                            WHEN status = 'completed'
                            THEN 1
                            ELSE 0
                        END
                    ) AS completed_stops,

                    SUM(
                        CASE
                            WHEN status = 'skipped'
                            THEN 1
                            ELSE 0
                        END
                    ) AS skipped_stops,

                    MIN(
                        predicted_arrival_time
                    ) AS first_predicted_arrival,

                    MAX(
                        predicted_service_end_time
                    ) AS last_predicted_service_end

                FROM delivery_stops

                GROUP BY
                    delivery_route_id

            ) ds
                ON ds.delivery_route_id = dr.id
        ");
    }
};