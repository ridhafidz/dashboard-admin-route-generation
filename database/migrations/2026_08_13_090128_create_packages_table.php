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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('tracking_number')->unique();
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('volume_m3', 8, 3)->nullable();
            $table->boolean('is_fragile')->default(false);
            $table->date('scheduled_date');
            $table->enum('status', ['pending', 'assigned', 'delivered', 'failed'])->default('pending');
            $table->timestamps();
            $table->index(['scheduled_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
