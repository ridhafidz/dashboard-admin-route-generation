<?php

namespace App\Filament\Widgets;

use App\Services\RouteControlTowerService;
use Filament\Widgets\Widget;

class DriverTrackingWidget extends Widget
{
    protected string $view =
        'filament.widgets.driver-tracking-widget';

    /*
    |--------------------------------------------------------------------------
    | FULL WIDTH
    |--------------------------------------------------------------------------
    */

    protected int|string|array $columnSpan = 'full';

    /*
    |--------------------------------------------------------------------------
    | SORT
    |--------------------------------------------------------------------------
    */

    protected static ?int $sort = 1;

    /*
    |--------------------------------------------------------------------------
    | VIEW DATA
    |--------------------------------------------------------------------------
    */

    protected function getViewData(): array
    {
        /*
         * Untuk live tracking Dashboard:
         *
         * - gunakan route hari ini
         * - ambil geometry dari RouteControlTowerService
         * - geometry tetap berasal dari OSRM
         */
        $controlTowerData =
            app(RouteControlTowerService::class)
                ->build(
                    today()->toDateString()
                );

        /*
         * Dashboard hanya menampilkan route yang:
         *
         * approval_status = approved
         *
         * dan status:
         * - planned  = sudah fix, menunggu driver mulai
         * - ongoing  = sedang berjalan/live
         */
        $routes =
            collect(
                $controlTowerData['routes']
                ?? []
            )
                ->filter(
                    function (array $route): bool {

                        $approvalStatus =
                            strtolower(
                                (string) (
                                    $route[
                                        'approval_status'
                                    ]
                                    ?? ''
                                )
                            );

                        $status =
                            strtolower(
                                (string) (
                                    $route['status']
                                    ?? ''
                                )
                            );

                        return
                            $approvalStatus
                                === 'approved'
                            &&
                            in_array(
                                $status,
                                [
                                    'planned',
                                    'ongoing',
                                ],
                                true
                            );
                    }
                )
                ->values()
                ->all();

        /*
         * Hitung summary awal.
         */
        $ongoingCount =
            collect($routes)
                ->where(
                    'status',
                    'ongoing'
                )
                ->count();

        $plannedCount =
            collect($routes)
                ->where(
                    'status',
                    'planned'
                )
                ->count();

        return [
            'trackingData' => [
                'route_date' =>
                    today()->toDateString(),

                'branch' =>
                    $controlTowerData['branch']
                    ?? null,

                'routes' =>
                    $routes,

                'route_count' =>
                    count($routes),

                'ongoing_count' =>
                    $ongoingCount,

                'waiting_count' =>
                    $plannedCount,
            ],

            /*
             * Sama seperti Control Tower.
             */
            'googleMapsBrowserKey' =>
                config(
                    'services.google_maps.browser_key'
                ),

            'googleMapsMapId' =>
                config(
                    'services.google_maps.map_id',
                    'DEMO_MAP_ID'
                ),
        ];
    }
}