<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('delivery_routes')
            ->whereIn(
                'status',
                [
                    'ongoing',
                    'completed',
                ]
            )
            ->where(
                'approval_status',
                'pending'
            )
            ->update([
                'approval_status' =>
                    'approved',

                'approved_at' =>
                    now(),
            ]);
    }

    public function down(): void
    {
        /*
         * Sengaja tidak dikembalikan ke pending.
         *
         * Migration ini hanya backfill data histori.
         */
    }
};