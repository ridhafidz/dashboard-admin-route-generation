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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('box_type', ['cold_storage', 'dry']);
            $table->unsignedInteger('units_per_carton');
            $table->unsignedInteger('units_per_pack')->nullable();
            $table->decimal('carton_length_cm', 8, 2)->nullable();
            $table->decimal('carton_width_cm', 8, 2)->nullable();
            $table->decimal('carton_height_cm', 8, 2)->nullable();
            $table->decimal('unit_volume_m3', 12, 8)->nullable();
            $table->decimal('carton_volume_m3', 12, 6)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['box_type', 'status'], 'products_box_type_status_idx');
            $table->index('name', 'products_name_idx');
            $table->index('status', 'idx_products_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
