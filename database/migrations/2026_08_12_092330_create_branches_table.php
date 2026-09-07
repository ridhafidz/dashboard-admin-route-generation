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
        Schema::create('branches', function (Blueprint $table) { 
            $table->id(); 
            $table->uuid('uuid')->unique();
            $table->string('cab_id', 50)->nullable(); 
            $table->string('name', 100);
            $table->string('region_id', 50)->nullable();
            $table->string('lokasi_cabang', 50)->nullable();
            $table->string('init_cab', 10)->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['active', 'inactive']) ->default('active'); 
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
