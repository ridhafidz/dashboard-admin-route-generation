<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Durasi layanan di store (menit). Dipakai Python untuk menghitung
            // latest_arrival = closing_time - service_duration_minutes.
            // Nullable; Python fallback ke 15 menit jika null.
            $table->unsignedSmallInteger('service_duration_minutes')
                ->nullable()
                ->after('closing_time');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('service_duration_minutes');
        });
    }
};
