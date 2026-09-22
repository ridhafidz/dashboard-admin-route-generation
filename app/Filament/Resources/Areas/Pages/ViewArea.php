<?php

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewArea extends ViewRecord
{
    protected static string $resource =
        AreaResource::class;


    protected string $view =
        'filament.resources.areas.pages.view-area';


    public function mount(
        int|string $record
    ): void {

        parent::mount(
            $record
        );


        /*
        |--------------------------------------------------------------------------
        | LOAD ANGGOTA AREA
        |--------------------------------------------------------------------------
        |
        | Hanya Store dengan:
        |
        | stores.area_id = areas.id
        |
        */

        $this
            ->getRecord()
            ->load([
                'branch',

                'stores' =>
                    fn ($query) =>
                        $query
                            ->orderBy('name'),
            ]);
    }


    public function getTitle(): string|Htmlable
    {
        return
            'Detail Area '
            . $this
                ->getRecord()
                ->code;
    }


    protected function getViewData(): array
    {
        $area =
            $this->getRecord();


        $stores =
            $area
                ->stores
                ->map(
                    function ($store): array {

                        return [

                            'id' =>
                                (int)
                                $store->id,

                            'code' =>
                                $store->code,

                            'name' =>
                                $store->name,

                            'address' =>
                                $store->address,

                            'latitude' =>
                                $store->latitude !== null

                                    ? (float)
                                        $store->latitude

                                    : null,

                            'longitude' =>
                                $store->longitude !== null

                                    ? (float)
                                        $store->longitude

                                    : null,

                            'opening_time' =>
                                $store->opening_time,

                            'closing_time' =>
                                $store->closing_time,
                        ];
                    }
                )
                ->values()
                ->all();


        /*
        |--------------------------------------------------------------------------
        | STORE YANG BISA DITAMPILKAN DI MAP
        |--------------------------------------------------------------------------
        */

        $mapStores =
            collect(
                $stores
            )
                ->filter(
                    function (
                        array $store
                    ): bool {

                        return
                            $store['latitude']
                            !== null

                            &&

                            $store['longitude']
                            !== null;
                    }
                )
                ->values()
                ->all();


        return [

            'area' =>
                $area,

            'stores' =>
                $stores,

            'mapStores' =>
                $mapStores,

            'googleMapsBrowserKey' =>
                config(
                    'services.google_maps.browser_key'
                )
                ??
                config(
                    'services.google_maps.key'
                ),
        ];
    }
}