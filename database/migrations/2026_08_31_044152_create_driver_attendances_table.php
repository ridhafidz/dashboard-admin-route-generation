<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('driver_id')
                ->constrained()
                ->cascadeOnDelete();

            // CHECK IN
            $table->dateTime('checkin_at');
            $table->decimal('checkin_latitude', 10, 7);
            $table->decimal('checkin_longitude', 10, 7);
            $table->string('checkin_photo')->nullable();

            // CHECK OUT
            $table->dateTime('checkout_at')->nullable();
            $table->decimal('checkout_latitude', 10, 7)->nullable();
            $table->decimal('checkout_longitude', 10, 7)->nullable();
            $table->string('checkout_photo')->nullable();

            $table->timestamps();

            $table->index([
                'driver_id',
                'checkin_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_attendances');
    }
};