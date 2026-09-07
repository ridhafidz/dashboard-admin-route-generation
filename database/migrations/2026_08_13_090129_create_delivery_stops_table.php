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
            $table->unsignedSmallInteger('sequence_order');
            $table->timestamp('predicted_arrival_time')->nullable();
            $table->timestamp('actual_arrival_time')->nullable();
            $table->enum('status', ['pending', 'arrived', 'completed', 'skipped'])->default('pending');
            $table->string('proof_of_delivery')->nullable();
            $table->timestamps();
            $table->unique(['delivery_route_id', 'sequence_order']);
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
