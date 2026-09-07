<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);

            $table->dropColumn([
                'employee_code',
                'shift_start_time',
                'shift_end_time',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->unique();
            $table->time('shift_start_time')->nullable();
            $table->time('shift_end_time')->nullable();
        });
    }
};