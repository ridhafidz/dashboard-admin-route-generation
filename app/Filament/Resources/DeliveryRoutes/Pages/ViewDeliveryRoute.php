<?php

namespace App\Filament\Resources\DeliveryRoutes\Pages;

use App\Filament\Resources\DeliveryRoutes\DeliveryRouteResource;
use App\Models\DeliveryRoute;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewDeliveryRoute extends ViewRecord
{
    protected static string $resource =
        DeliveryRouteResource::class;

    protected string $view =
        'filament.resources.delivery-routes.pages.view-delivery-route';

    public function mount(
        int|string $record
    ): void {
        parent::mount($record);

        /*
         * Load seluruh data yang diperlukan
         * halaman Control Tower.
         */
        $this->getRecord()->load([
            'branch',
            'driver',
            'vehicle.vehicleType',

            'deliveryStops.store',

            'deliveryStops.packages.order',

            /*
             * Product sudah kita tambahkan
             * pada Package model sebelumnya.
             */
            'deliveryStops.packages.product',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return
            'Detail Delivery Route #'
            . $this->getRecord()->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->icon(
                    'heroicon-o-arrow-left'
                )
                ->color('gray')
                ->url(
                    DeliveryRouteResource::getUrl(
                        'index'
                    )
                ),
        ];
    }

    protected function getViewData(): array
    {
        /** @var DeliveryRoute $route */
        $route =
            $this->getRecord();

        /*
        |--------------------------------------------------------------------------
        | STOP
        |--------------------------------------------------------------------------
        */

        $stops =
            $route
                ->deliveryStops
                ->sortBy(
                    'sequence_order'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | PACKAGE UNIQUE
        |--------------------------------------------------------------------------
        */

        $packages =
            $stops
                ->flatMap(
                    fn ($stop) =>
                        $stop->packages
                )
                ->unique('id')
                ->values();

        /*
        |--------------------------------------------------------------------------
        | LOAD SUMMARY
        |--------------------------------------------------------------------------
        */

        $totalVolumeM3 =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->volume_m3
                        ?? 0
                    )
            );

        $totalWeightKg =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->weight_kg
                        ?? 0
                    )
            );

        $totalPrice =
            (float)
            $packages->sum(
                fn ($package): float =>
                    (float) (
                        $package
                            ->total_price
                        ?? 0
                    )
            );

        $capacityVolumeM3 =
            (float) (
                $route
                    ->vehicle
                    ?->vehicleType
                    ?->volume_m3
                ?? 0
            );

        $volumeUtilization =
            $capacityVolumeM3 > 0
                ? (
                    $totalVolumeM3
                    /
                    $capacityVolumeM3
                ) * 100
                : 0.0;

        /*
        |--------------------------------------------------------------------------
        | STOP DETAIL
        |--------------------------------------------------------------------------
        */

        $stopRows =
            $stops
                ->map(
                    function ($stop): array {

                        return [

                            'id' =>
                                $stop->id,

                            'sequence' =>
                                (int)
                                $stop
                                    ->sequence_order,

                            'status' =>
                                $this->enumLabel(
                                    $stop->status
                                ),

                            'status_value' =>
                                $this->enumValue(
                                    $stop->status
                                ),

                            'store' => [

                                'id' =>
                                    $stop
                                        ->store
                                        ?->id,

                                'code' =>
                                    $stop
                                        ->store
                                        ?->code,

                                'name' =>
                                    $stop
                                        ->store
                                        ?->name,

                                'address' =>
                                    $stop
                                        ->store
                                        ?->address,

                                'latitude' =>
                                    $stop
                                        ->store
                                        ?->latitude
                                    !== null

                                        ? (float)
                                            $stop
                                                ->store
                                                ->latitude

                                        : null,

                                'longitude' =>
                                    $stop
                                        ->store
                                        ?->longitude
                                    !== null

                                        ? (float)
                                            $stop
                                                ->store
                                                ->longitude

                                        : null,
                            ],

                            'predicted_arrival' =>
                                $stop
                                    ->predicted_arrival_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'service_start' =>
                                $stop
                                    ->predicted_service_start_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'service_end' =>
                                $stop
                                    ->predicted_service_end_time
                                    ?->format(
                                        'H:i'
                                    ),

                            'waiting_minutes' =>
                                (int) (
                                    $stop
                                        ->predicted_waiting_minutes
                                    ?? 0
                                ),

                            'packages' =>
                                $stop
                                    ->packages
                                    ->map(
                                        function (
                                            $package
                                        ): array {

                                            return [

                                                'tracking_number' =>
                                                    $package
                                                        ->tracking_number,

                                                'order_number' =>
                                                    $package
                                                        ->order
                                                        ?->order_number,

                                                'product_code' =>
                                                    $package
                                                        ->product
                                                        ?->code,

                                                'product_name' =>
                                                    $package
                                                        ->item,

                                                'quantity' =>
                                                    (int)
                                                    $package
                                                        ->quantity,

                                                'uom' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->uom
                                                            ?? null
                                                        ),

                                                'box_type' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->box_type
                                                            ?? null
                                                        ),

                                                'weight_kg' =>
                                                    (float) (
                                                        $package
                                                            ->weight_kg
                                                        ?? 0
                                                    ),

                                                'volume_m3' =>
                                                    (float) (
                                                        $package
                                                            ->volume_m3
                                                        ?? 0
                                                    ),

                                                'total_price' =>
                                                    $package
                                                        ->total_price
                                                    !== null

                                                        ? (float)
                                                            $package
                                                                ->total_price

                                                        : null,

                                                'status' =>
                                                    $this
                                                        ->enumLabel(
                                                            $package
                                                                ->status
                                                        ),
                                            ];
                                        }
                                    )
                                    ->values()
                                    ->all(),
                        ];
                    }
                )
                ->all();

        /*
        |--------------------------------------------------------------------------
        | GOOGLE MAP PAYLOAD
        |--------------------------------------------------------------------------
        */

        $mapData = [

            'route_id' =>
                $route->id,

            'branch' => [

                'name' =>
                    $route
                        ->branch
                        ?->name,

                'code' =>
                    $route
                        ->branch
                        ?->init_cab,

                'latitude' =>
                    $route
                        ->branch
                        ?->latitude
                    !== null

                        ? (float)
                            $route
                                ->branch
                                ->latitude

                        : null,

                'longitude' =>
                    $route
                        ->branch
                        ?->longitude
                    !== null

                        ? (float)
                            $route
                                ->branch
                                ->longitude

                        : null,
            ],

            'stops' =>
                collect(
                    $stopRows
                )
                    ->filter(
                        fn (
                            array $stop
                        ): bool =>
                            $stop[
                                'store'
                            ][
                                'latitude'
                            ] !== null

                            &&

                            $stop[
                                'store'
                            ][
                                'longitude'
                            ] !== null
                    )
                    ->values()
                    ->all(),
        ];

        return [

            'route' =>
                $route,

            'routeStatusLabel' =>
                $this->enumLabel(
                    $route->status
                ),

            'vehicleCategoryLabel' =>
                $this->enumLabel(
                    $route
                        ->vehicle
                        ?->vehicleType
                        ?->category
                ),

            'boxTypeLabel' =>
                $this->enumLabel(
                    $route
                        ->vehicle
                        ?->vehicleType
                        ?->box_type
                ),

            'predictedDurationLabel' =>
                $this->durationLabel(
                    (int) (
                        $route
                            ->predicted_duration_minutes
                        ?? 0
                    )
                ),

            'stops' =>
                $stopRows,

            'packageCount' =>
                $packages->count(),

            'totalVolumeM3' =>
                $totalVolumeM3,

            'totalWeightKg' =>
                $totalWeightKg,

            'totalPrice' =>
                $totalPrice,

            'capacityVolumeM3' =>
                $capacityVolumeM3,

            'volumeUtilization' =>
                $volumeUtilization,

            'mapData' =>
                $mapData,

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

    private function durationLabel(
        int $minutes
    ): string {

        $minutes =
            max(
                0,
                $minutes
            );

        $hours =
            intdiv(
                $minutes,
                60
            );

        $remainingMinutes =
            $minutes % 60;

        if ($hours <= 0) {

            return
                $remainingMinutes
                . ' menit';
        }

        return
            $hours
            . ' jam '
            . $remainingMinutes
            . ' menit';
    }

    private function enumValue(
        mixed $value
    ): ?string {

        if (
            $value instanceof
            BackedEnum
        ) {
            return
                (string)
                $value->value;
        }

        if ($value === null) {
            return null;
        }

        return
            (string)
            $value;
    }

    private function enumLabel(
        mixed $value
    ): string {

        if ($value === null) {
            return '-';
        }

        if (
            is_object($value)
            &&
            method_exists(
                $value,
                'getLabel'
            )
        ) {

            return
                (string)
                $value->getLabel();
        }

        if (
            $value instanceof
            BackedEnum
        ) {

            return
                str(
                    (string)
                    $value->value
                )
                    ->replace(
                        '_',
                        ' '
                    )
                    ->title()
                    ->toString();
        }

        return
            str(
                (string)
                $value
            )
                ->replace(
                    '_',
                    ' '
                )
                ->title()
                ->toString();
    }
}