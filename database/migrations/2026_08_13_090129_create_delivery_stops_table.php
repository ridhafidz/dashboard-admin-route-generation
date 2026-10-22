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
        Schema::create('delivery_stops', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('delivery_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained();
            $table->string('source_customer_code', 50)->nullable();
            $table->string('box_type', 20)->nullable();
            $table->unsignedSmallInteger('sequence_order');
            $table->timestamp('predicted_arrival_time')->nullable();
            $table->timestamp('predicted_service_start_time')->nullable();
            $table->timestamp('predicted_service_end_time')->nullable();
            $table->unsignedInteger('predicted_waiting_minutes')->nullable();
            $table->timestamp('actual_arrival_time')->nullable();
            $table->timestamp('actual_departure_time')->nullable();
            $table->json('source_so_numbers')->nullable();
            $table->json('source_dates')->nullable()->comment('Tanggal asli per SO, array paralel dengan source_so_numbers. Backlog-aware.');
            $table->enum('status', ['pending', 'arrived', 'completed', 'skipped'])->default('pending');
            $table->string('proof_of_delivery')->nullable();
            $table->timestamps();

            $table->unique(['delivery_route_id', 'sequence_order']);
            $table->index(['delivery_route_id', 'sequence_order'], 'idx_stops_route_sequence');
            $table->index(['delivery_route_id', 'status'], 'idx_stops_route_status');
            $table->index('store_id', 'idx_stops_store');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_stops');
    }
};
