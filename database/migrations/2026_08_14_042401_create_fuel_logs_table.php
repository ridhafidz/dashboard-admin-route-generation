<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('delivery_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fuel_type');
            $table->decimal('liters', 8, 2);
            $table->decimal('price_per_liter', 10, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamp('filled_at');
            $table->timestamps();

            $table->index(['vehicle_id', 'filled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
