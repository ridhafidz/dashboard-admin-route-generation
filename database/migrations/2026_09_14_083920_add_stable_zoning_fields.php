<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table
                ->string('status', 20)
                ->default('draft')
                ->after('polygon_geojson');

            $table
                ->string('generation_source', 20)
                ->default('manual')
                ->after('status');

            $table
                ->timestamp('approved_at')
                ->nullable()
                ->after('generation_source');
        });


        Schema::table('stores', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('branch_id')
                ->nullable()
                ->after('area_id');

            $table->index(
                'branch_id',
                'idx_stores_branch_id'
            );
        });


        /*
         * Backfill Store existing.
         *
         * Area existing sudah mempunyai branch_id,
         * jadi Store lama tidak kehilangan cabangnya.
         */
        DB::statement("
            UPDATE stores s
            INNER JOIN areas a
                ON a.id = s.area_id
            SET s.branch_id = a.branch_id
            WHERE s.branch_id IS NULL
        ");
    }


    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(
                'idx_stores_branch_id'
            );

            $table->dropColumn(
                'branch_id'
            );
        });


        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'generation_source',
                'approved_at',
            ]);
        });
    }
};