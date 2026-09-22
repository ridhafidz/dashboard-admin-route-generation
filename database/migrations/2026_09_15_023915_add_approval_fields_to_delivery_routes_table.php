<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'delivery_routes',
            function (Blueprint $table): void {

                $table
                    ->string(
                        'approval_status',
                        20
                    )
                    ->default('pending')
                    ->after('status');

                $table
                    ->timestamp('approved_at')
                    ->nullable()
                    ->after('approval_status');

                $table
                    ->unsignedBigInteger('approved_by')
                    ->nullable()
                    ->after('approved_at');

                $table->index(
                    [
                        'route_date',
                        'approval_status',
                    ],
                    'idx_delivery_routes_approval'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'delivery_routes',
            function (Blueprint $table): void {

                $table->dropIndex(
                    'idx_delivery_routes_approval'
                );

                $table->dropColumn([
                    'approval_status',
                    'approved_at',
                    'approved_by',
                ]);
            }
        );
    }
};
