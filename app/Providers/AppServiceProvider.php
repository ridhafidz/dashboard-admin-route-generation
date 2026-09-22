<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
 public function boot(): void
    {
    /*
    |--------------------------------------------------------------------------
    | LOCAL PERFORMANCE MONITOR
    |--------------------------------------------------------------------------
    |
    | Hanya aktif pada APP_ENV=local.
    |
    | Mengukur:
    | - total request
    | - jumlah query
    | - total waktu query
    | - estimasi waktu PHP/Laravel
    | - memory
    | - slow query >= 100 ms
    |
    */

        if (
            ! app()->environment('local')
            ||
            app()->runningInConsole()
            ||
            ! env('PERFORMANCE_MONITOR', false)
        ) {
            return;
        }

        $queryCount =
            0;


        $queryTimeMs =
            0.0;


        $slowQueries =
            [];


        DB::listen(
            function (
                $query
            ) use (
                &$queryCount,
                &$queryTimeMs,
                &$slowQueries
            ): void {

                $queryCount++;

                $queryTimeMs +=
                    (float)
                    $query->time;

                if (
                    $query->time
                    >=
                    100
                ) {

                    /*
                     * Maksimal simpan 5 slow query
                     * agar log tidak terlalu besar.
                     */

                    if (
                        count(
                            $slowQueries
                        )
                        <
                        5
                    ) {

                        $slowQueries[] = [

                            'ms' =>
                            round(
                                $query->time,
                                2
                            ),

                            'sql' =>
                            $query->sql,
                        ];
                    }
                }
            }
        );


        app()->terminating(
            function () use (
                &$queryCount,
                &$queryTimeMs,
                &$slowQueries
            ): void {

                $request =
                    request();


                /*
                 * Fokus:
                 *
                 * - Filament Admin
                 * - Livewire polling/action
                 */

                $shouldMonitor =

                    $request->is(
                        'admin'
                    )

                    ||

                    $request->is(
                        'admin/*'
                    )

                    ||

                    $request->is(
                        'livewire/*'
                    );


                if (! $shouldMonitor) {
                    return;
                }


                $durationMs =

                    defined(
                        'LARAVEL_START'
                    )

                    ? (
                        microtime(true)
                        -
                        LARAVEL_START
                    ) * 1000

                    : 0;


                Log::info(
                    '[PERF]',
                    [

                        'method' =>
                            $request->method(),

                        'path' =>
                            $request->path(),

                        'duration_ms' =>
                            round(
                                $durationMs,
                                2
                            ),

                        'queries' =>
                            $queryCount,

                        'query_ms' =>
                            round(
                                $queryTimeMs,
                                2
                            ),

                        'php_ms' =>
                        round(
                            max(
                                0,
                                $durationMs
                                -
                                $queryTimeMs
                            ),
                            2
                        ),

                        'memory_mb' =>
                        round(
                            memory_get_peak_usage(
                                true
                            )
                            /
                            1024
                            /
                            1024,
                            2
                        ),

                        'slow_queries' =>
                            $slowQueries,
                    ]
                );
            }
        );
    }
}
