<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Jam mulai operasional cabang — dipakai sebagai start_time kendaraan.
            // Default 06:00 agar branch lama tidak perlu diupdate manual.
            $table->time('start_time')->nullable()->default('06:00')->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
    }
};
