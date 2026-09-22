<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('delivery_logs')
            &&
            Schema::hasColumn(
                'delivery_logs',
                'event_type'
            )
        ) {
            DB::statement("
                ALTER TABLE delivery_logs
                MODIFY event_type VARCHAR(30) NOT NULL
            ");
        }
    }

    public function down(): void
    {
        /*
         * Sengaja tidak dikembalikan ke ENUM.
         *
         * Setelah sistem memiliki event baru seperti:
         * gps_ping / delivered / route_completed,
         * rollback ke ENUM berpotensi menghilangkan data.
         */
    }
};