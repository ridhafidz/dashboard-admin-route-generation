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
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->date('source_date')->nullable();
            $table->date('route_date');
            $table->enum('status', ['planned', 'ongoing', 'completed', 'cancelled'])->default('planned');
            $table->string('approval_status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedInteger('predicted_duration_minutes')->nullable();
            $table->decimal('predicted_cost', 12, 2)->nullable();
            $table->unsignedInteger('predicted_package_count')->nullable();

            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->unsignedInteger('actual_package_count')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('branch_id', 'idx_delivery_routes_branch_id');
            $table->index('route_date', 'idx_delivery_routes_route_date');
            $table->index('status', 'idx_delivery_routes_status');
            $table->index('approval_status', 'idx_delivery_routes_approval_status');
            $table->index(['route_date', 'driver_id']);
            $table->index(['branch_id', 'route_date', 'status'], 'idx_routes_branch_date_status');
            $table->index(['route_date', 'approval_status'], 'idx_delivery_routes_approval');
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
