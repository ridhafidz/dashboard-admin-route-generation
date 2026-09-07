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
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('driver_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('area_id')->constrained();
            $table->date('route_date');
            $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');

            $table->unsignedInteger('predicted_duration_minutes')->nullable();
            $table->decimal('predicted_cost', 12, 2)->nullable();
            $table->unsignedInteger('predicted_package_count')->nullable();

            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->unsignedInteger('actual_package_count')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['route_date', 'driver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_routes');
    }
};
