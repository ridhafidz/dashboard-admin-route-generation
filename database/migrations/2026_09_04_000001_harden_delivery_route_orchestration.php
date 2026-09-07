<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('uuid')
                ->constrained()
                ->nullOnDelete();

            $table->index(['branch_id', 'route_date', 'status'], 'delivery_routes_branch_date_status_idx');
        });

        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable()->change();
        });

        DB::table('delivery_routes')
            ->join('drivers', 'drivers.id', '=', 'delivery_routes.driver_id')
            ->whereNull('delivery_routes.branch_id')
            ->whereNotNull('drivers.branch_id')
            ->select('delivery_routes.id', 'drivers.branch_id')
            ->orderBy('delivery_routes.id')
            ->get()
            ->each(function ($route) {
                DB::table('delivery_routes')
                    ->where('id', $route->id)
                    ->update(['branch_id' => $route->branch_id]);
            });

        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->timestamp('predicted_service_start_time')->nullable()->after('predicted_arrival_time');
            $table->timestamp('predicted_service_end_time')->nullable()->after('predicted_service_start_time');
            $table->unsignedSmallInteger('predicted_waiting_minutes')->nullable()->after('predicted_service_end_time');
            $table->timestamp('actual_departure_time')->nullable()->after('actual_arrival_time');
        });

        Schema::table('delivery_stop_package', function (Blueprint $table) {
            $table->unique(['delivery_stop_id', 'package_id'], 'delivery_stop_package_stop_package_unique');
        });

        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->decimal('capacity_weight_kg', 10, 2)->nullable()->after('volume_m3');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn('capacity_weight_kg');
        });

        Schema::table('delivery_stop_package', function (Blueprint $table) {
            $table->dropUnique('delivery_stop_package_stop_package_unique');
        });

        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropColumn([
                'predicted_service_start_time',
                'predicted_service_end_time',
                'predicted_waiting_minutes',
                'actual_departure_time',
            ]);
        });

        Schema::table('delivery_routes', function (Blueprint $table) {
            $table->dropIndex('delivery_routes_branch_date_status_idx');
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
