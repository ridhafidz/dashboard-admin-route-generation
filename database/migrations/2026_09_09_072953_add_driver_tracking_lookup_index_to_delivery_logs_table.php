<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_logs', function (Blueprint $table) {
            $table->index(
                [
                    'delivery_route_id',
                    'event_type',
                    'id',
                ],
                'idx_delivery_logs_tracking_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('delivery_logs', function (Blueprint $table) {
            $table->dropIndex(
                'idx_delivery_logs_tracking_lookup'
            );
        });
    }
};