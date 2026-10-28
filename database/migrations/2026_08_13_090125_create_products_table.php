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
            $table->enum('box_type', ['cold_storage', 'dry', 'khusus']);
            $table->decimal('carton_length_cm', 8, 2)->nullable();
            $table->decimal('carton_width_cm', 8, 2)->nullable();
            $table->decimal('carton_height_cm', 8, 2)->nullable();
            $table->unsignedInteger('units_per_carton');
            $table->unsignedInteger('units_per_pack')->nullable();
            $table->decimal('unit_volume_cm3', 15, 2)->nullable();
            $table->decimal('carton_volume_cm3', 15, 2)->nullable();
            $table->timestamps();

            $table->index('box_type', 'products_box_type_idx');
            $table->index('name', 'products_name_idx');
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
