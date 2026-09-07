<x-filament-panels::page>

    @php
        $routeStatusValue =
            $route->status instanceof \BackedEnum
                ? $route->status->value
                : (string) $route->status;
    @endphp


    {{-- ======================================================
         SUMMARY
    ======================================================= --}}

    <div class="dr-summary-grid">

        <div class="dr-card">

            <div class="dr-label">
                Status
            </div>

            <div class="dr-value">

                <span
                    class="
                        dr-status
                        dr-status-{{ $routeStatusValue }}
                    "
                >
                    {{ $routeStatusLabel }}
                </span>

            </div>

            <div class="dr-subvalue">
                {{ $route->route_date?->format('d M Y') ?? '-' }}
            </div>

        </div>


        <div class="dr-card">

            <div class="dr-label">
                Driver
            </div>

            <div class="dr-value">
                {{ $route->driver?->name ?? '-' }}
            </div>

            <div class="dr-subvalue">
                {{ $route->driver?->phone ?? '-' }}
            </div>

        </div>


        <div class="dr-card">

            <div class="dr-label">
                Vehicle
            </div>

            <div class="dr-value">
                {{ $route->vehicle?->plate_number ?? '-' }}
            </div>

            <div class="dr-subvalue">
                {{ $vehicleCategoryLabel }}
                ·
                {{ $boxTypeLabel }}
            </div>

        </div>


        <div class="dr-card">

            <div class="dr-label">
                Kapasitas Volume
            </div>

            <div class="dr-value">

                {{ number_format(
                    $totalVolumeM3,
                    6,
                    ',',
                    '.'
                ) }}

                /

                {{ number_format(
                    $capacityVolumeM3,
                    3,
                    ',',
                    '.'
                ) }}

                m³

            </div>

            <div
                class="dr-progress-track"
            >

                <div
                    class="dr-progress-bar"
                    style="
                        width:
                        {{
                            min(
                                100,
                                max(
                                    0,
                                    $volumeUtilization
                                )
                            )
                        }}%
                    "
                ></div>

            </div>

            <div class="dr-subvalue">

                Utilisasi

                {{ number_format(
                    $volumeUtilization,
                    2,
                    ',',
                    '.'
                ) }}%

            </div>

        </div>


        <div class="dr-card">

            <div class="dr-label">
                Muatan
            </div>

            <div class="dr-value">
                {{ $packageCount }} package
            </div>

            <div class="dr-subvalue">

                {{ number_format(
                    $totalWeightKg,
                    4,
                    ',',
                    '.'
                ) }}
                kg

            </div>

        </div>


        <div class="dr-card">

            <div class="dr-label">
                Prediksi Durasi
            </div>

            <div class="dr-value">
                {{ $predictedDurationLabel }}
            </div>

            <div class="dr-subvalue">
                {{ count($stops) }} stop
            </div>

        </div>

    </div>



    {{-- ======================================================
         MAP + TIMELINE
    ======================================================= --}}

    <div class="dr-main-grid">


        {{-- GOOGLE MAP --}}
        <section class="dr-panel dr-map-panel">

            <div class="dr-panel-header">

                <div>

                    <h2>
                        Visualisasi Rute
                    </h2>

                    <p>
                        Urutan marker mengikuti hasil OR-Tools
                        dan tidak dioptimasi ulang oleh Google Maps.
                    </p>

                </div>


                <div class="dr-map-metrics">

                    <div>

                        <span class="dr-label">
                            Jarak Google
                        </span>

                        <strong
                            id="route-map-distance"
                        >
                            Menghitung...
                        </strong>

                    </div>


                    <div>

                        <span class="dr-label">
                            Waktu Berkendara
                        </span>

                        <strong
                            id="route-map-duration"
                        >
                            Menghitung...
                        </strong>

                    </div>

                </div>

            </div>


            @if (blank($googleMapsBrowserKey))

                <div class="dr-alert">

                    GOOGLE_MAPS_BROWSER_KEY /
                    GOOGLE_MAPS_API_KEY
                    belum dikonfigurasi.

                </div>

            @endif


            <div
                id="delivery-route-map"
                wire:ignore
                class="dr-map"
            ></div>


            <div
                id="route-map-error"
                class="dr-map-error"
                hidden
            ></div>

        </section>



        {{-- ==================================================
             ROUTE TIMELINE
        =================================================== --}}

        <section class="dr-panel">

            <div class="dr-panel-header">

                <div>

                    <h2>
                        Urutan Pengiriman
                    </h2>

                    <p>
                        Depot → toko sesuai sequence
                        → kembali ke depot.
                    </p>

                </div>

            </div>


            <div class="dr-timeline">


                {{-- START DEPOT --}}

                <div class="dr-timeline-row">

                    <div
                        class="
                            dr-timeline-marker
                            dr-depot-marker
                        "
                    >
                        D
                    </div>


                    <div class="dr-stop-card">

                        <div class="dr-stop-head">

                            <div>

                                <strong>

                                    {{
                                        $route
                                            ->branch
                                            ?->init_cab
                                        ?? 'DEPOT'
                                    }}

                                    -

                                    {{
                                        $route
                                            ->branch
                                            ?->name
                                        ?? 'Branch'
                                    }}

                                </strong>

                                <div class="dr-muted">
                                    Titik keberangkatan
                                </div>

                            </div>


                            <span class="dr-mini-badge">
                                START
                            </span>

                        </div>

                    </div>

                </div>



                {{-- STORE STOPS --}}

                @forelse ($stops as $stop)

                    <div class="dr-timeline-row">


                        <div class="dr-timeline-marker">
                            {{ $stop['sequence'] }}
                        </div>


                        <div class="dr-stop-card">


                            <div class="dr-stop-head">

                                <div>

                                    <strong>
                                        {{
                                            $stop[
                                                'store'
                                            ][
                                                'name'
                                            ]
                                            ?? '-'
                                        }}
                                    </strong>


                                    <div class="dr-muted">

                                        {{
                                            $stop[
                                                'store'
                                            ][
                                                'code'
                                            ]
                                            ?? '-'
                                        }}

                                    </div>

                                </div>


                                <span class="dr-mini-badge">
                                    {{ $stop['status'] }}
                                </span>

                            </div>



                            <div class="dr-address">

                                {{
                                    $stop[
                                        'store'
                                    ][
                                        'address'
                                    ]
                                    ?? '-'
                                }}

                            </div>



                            {{-- TIME --}}

                            <div class="dr-time-grid">

                                <div>

                                    <span>
                                        Arrival
                                    </span>

                                    <strong>
                                        {{
                                            $stop[
                                                'predicted_arrival'
                                            ]
                                            ?? '-'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Service
                                    </span>

                                    <strong>

                                        {{
                                            $stop[
                                                'service_start'
                                            ]
                                            ?? '-'
                                        }}

                                        -

                                        {{
                                            $stop[
                                                'service_end'
                                            ]
                                            ?? '-'
                                        }}

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Waiting
                                    </span>

                                    <strong>

                                        {{
                                            $stop[
                                                'waiting_minutes'
                                            ]
                                        }}
                                        menit

                                    </strong>

                                </div>

                            </div>



                            {{-- PACKAGE / SO --}}

                            <div class="dr-packages">

                                @foreach (
                                    $stop['packages']
                                    as $package
                                )

                                    <div class="dr-package-row">


                                        <div class="dr-package-main">

                                            <strong>

                                                @if (
                                                    $package[
                                                        'product_code'
                                                    ]
                                                )

                                                    {{
                                                        $package[
                                                            'product_code'
                                                        ]
                                                    }}
                                                    -

                                                @endif

                                                {{
                                                    $package[
                                                        'product_name'
                                                    ]
                                                }}

                                            </strong>


                                            <span>

                                                SO
                                                {{
                                                    $package[
                                                        'order_number'
                                                    ]
                                                    ?? '-'
                                                }}

                                                ·

                                                {{
                                                    $package[
                                                        'tracking_number'
                                                    ]
                                                    ?? '-'
                                                }}

                                            </span>

                                        </div>


                                        <div class="dr-package-qty">

                                            {{
                                                number_format(
                                                    $package[
                                                        'quantity'
                                                    ],
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}

                                            {{
                                                $package[
                                                    'uom'
                                                ]
                                            }}

                                        </div>


                                        <div class="dr-package-metrics">

                                            <span>

                                                {{
                                                    number_format(
                                                        $package[
                                                            'weight_kg'
                                                        ],
                                                        4,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                kg

                                            </span>


                                            <span>

                                                {{
                                                    number_format(
                                                        $package[
                                                            'volume_m3'
                                                        ],
                                                        6,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                m³

                                            </span>

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    </div>


                @empty

                    <div class="dr-empty">

                        Belum ada delivery stop
                        pada route ini.

                    </div>

                @endforelse



                {{-- RETURN DEPOT --}}

                <div class="dr-timeline-row">

                    <div
                        class="
                            dr-timeline-marker
                            dr-depot-marker
                        "
                    >
                        D
                    </div>


                    <div class="dr-stop-card">

                        <div class="dr-stop-head">

                            <div>

                                <strong>

                                    {{
                                        $route
                                            ->branch
                                            ?->init_cab
                                        ?? 'DEPOT'
                                    }}

                                    -

                                    {{
                                        $route
                                            ->branch
                                            ?->name
                                        ?? 'Branch'
                                    }}

                                </strong>

                                <div class="dr-muted">
                                    Kembali ke depot
                                </div>

                            </div>


                            <span class="dr-mini-badge">
                                FINISH
                            </span>

                        </div>

                    </div>

                </div>


            </div>

        </section>

    </div>



    {{-- ======================================================
         VALUE SUMMARY
    ======================================================= --}}

    @if ($totalPrice > 0)

        <section class="dr-panel">

            <div class="dr-panel-header">

                <div>

                    <h2>
                        Ringkasan Nilai Sales Order
                    </h2>

                    <p>
                        Total package yang ter-attach
                        pada route ini.
                    </p>

                </div>


                <strong class="dr-total-price">

                    Rp

                    {{
                        number_format(
                            $totalPrice,
                            2,
                            ',',
                            '.'
                        )
                    }}

                </strong>

            </div>

        </section>

    @endif


</x-filament-panels::page>



{{-- =========================================================
     STYLE
========================================================= --}}

@push('styles')

<style>

.dr-summary-grid {
    display: grid;
    grid-template-columns:
        repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.dr-card,
.dr-panel,
.dr-stop-card {
    border: 1px solid rgb(229 231 235);
    background: white;
    border-radius: 14px;
}

.dark .dr-card,
.dark .dr-panel,
.dark .dr-stop-card {
    border-color: rgb(55 65 81);
    background: rgb(17 24 39);
}

.dr-card {
    padding: 14px;
    min-width: 0;
}

.dr-label {
    display: block;
    color: rgb(107 114 128);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 5px;
}

.dr-value {
    font-size: 16px;
    font-weight: 700;
    color: rgb(17 24 39);
}

.dr-subvalue {
    color: rgb(107 114 128);
    font-size: 12px;
    margin-top: 5px;
}

.dr-progress-track {
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: rgb(229 231 235);
    overflow: hidden;
    margin-top: 8px;
}

.dr-progress-bar {
    height: 100%;
    background: rgb(245 158 11);
    border-radius: inherit;
}

.dr-status,
.dr-mini-badge {
    display: inline-flex;
    border-radius: 999px;
    padding: 3px 9px;
    font-size: 11px;
    font-weight: 700;
    background: rgb(243 244 246);
    color: rgb(55 65 81);
}

.dr-status-ongoing {
    background: rgb(254 243 199);
    color: rgb(146 64 14);
}

.dr-status-completed {
    background: rgb(220 252 231);
    color: rgb(22 101 52);
}

.dr-status-cancelled {
    background: rgb(254 226 226);
    color: rgb(153 27 27);
}

.dr-main-grid {
    display: grid;
    grid-template-columns:
        minmax(0, 1.45fr)
        minmax(380px, .85fr);
    gap: 16px;
    align-items: start;
}

.dr-panel {
    overflow: hidden;
    margin-bottom: 16px;
}

.dr-panel-header {
    padding: 16px 18px;
    border-bottom:
        1px solid rgb(229 231 235);

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.dr-panel h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.dr-panel-header p {
    margin: 4px 0 0;
    font-size: 12px;
    color: rgb(107 114 128);
}

.dr-map {
    width: 100%;
    min-height: 650px;
    height: calc(100vh - 340px);
    background: rgb(243 244 246);
}

.dr-map-metrics {
    display: flex;
    gap: 18px;
    text-align: right;
}

.dr-map-metrics strong {
    display: block;
    font-size: 13px;
}

.dr-alert,
.dr-map-error {
    padding: 12px 16px;
    background: rgb(254 242 242);
    color: rgb(153 27 27);
    font-size: 13px;
}

.dr-timeline {
    padding: 18px;
}

.dr-timeline-row {
    position: relative;
    display: grid;

    grid-template-columns:
        34px minmax(0, 1fr);

    gap: 12px;
    padding-bottom: 18px;
}

.dr-timeline-row:not(:last-child)::after {
    content: '';
    position: absolute;

    left: 16px;
    top: 34px;
    bottom: 0;

    width: 2px;
    background: rgb(229 231 235);
}

.dr-timeline-marker {
    position: relative;
    z-index: 1;

    width: 34px;
    height: 34px;

    border-radius: 999px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;
    font-weight: 800;

    color: white;
    background: rgb(245 158 11);
}

.dr-depot-marker {
    background: rgb(37 99 235);
}

.dr-stop-card {
    padding: 14px;
}

.dr-stop-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.dr-muted,
.dr-address {
    color: rgb(107 114 128);
    font-size: 12px;
}

.dr-address {
    margin-top: 8px;
    line-height: 1.5;
}

.dr-time-grid {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 8px;
    margin-top: 12px;
}

.dr-time-grid > div {
    padding: 8px;
    border-radius: 9px;
    background: rgb(249 250 251);
}

.dr-time-grid span {
    display: block;
    font-size: 10px;
    color: rgb(107 114 128);
}

.dr-time-grid strong {
    font-size: 12px;
}

.dr-packages {
    margin-top: 12px;

    border-top:
        1px solid rgb(229 231 235);
}

.dr-package-row {
    display: grid;

    grid-template-columns:
        minmax(0, 1fr) auto;

    gap: 7px 12px;

    padding: 10px 0;

    border-bottom:
        1px dashed rgb(229 231 235);
}

.dr-package-row:last-child {
    border-bottom: 0;
}

.dr-package-main strong {
    display: block;
    font-size: 12px;
}

.dr-package-main span,
.dr-package-metrics,
.dr-package-qty {
    font-size: 11px;
    color: rgb(107 114 128);
}

.dr-package-qty {
    font-weight: 700;
}

.dr-package-metrics {
    grid-column: 1 / -1;

    display: flex;
    gap: 12px;
}

.dr-total-price {
    font-size: 18px;
}


/* DARK MODE */

.dark .dr-value,
.dark .dr-panel h2,
.dark .dr-stop-head strong,
.dark .dr-package-main strong,
.dark .dr-time-grid strong {
    color: rgb(243 244 246);
}

.dark .dr-label,
.dark .dr-muted,
.dark .dr-subvalue,
.dark .dr-address {
    color: rgb(156 163 175);
}

.dark .dr-progress-track,
.dark .dr-timeline-row:not(:last-child)::after {
    background: rgb(55 65 81);
}

.dark .dr-panel-header,
.dark .dr-packages,
.dark .dr-package-row {
    border-color: rgb(55 65 81);
}

.dark .dr-time-grid > div {
    background: rgb(31 41 55);
}


/* RESPONSIVE */

@media (max-width: 1280px) {

    .dr-summary-grid {
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .dr-main-grid {
        grid-template-columns: 1fr;
    }

    .dr-map {
        min-height: 520px;
        height: 60vh;
    }
}

@media (max-width: 700px) {

    .dr-summary-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .dr-panel-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .dr-map-metrics {
        width: 100%;
        justify-content: space-between;
        text-align: left;
    }

    .dr-time-grid {
        grid-template-columns: 1fr;
    }
}

</style>

@endpush



{{-- =========================================================
     GOOGLE MAPS
========================================================= --}}
@include(
    'filament.partials.google-maps-loader'
)

@push('scripts')

<script>

window.__deliveryRouteMapData =
    @js($mapData);

window.__deliveryRouteGoogleMapsKey =
    @js($googleMapsBrowserKey);

window.__deliveryRouteGoogleMapId =
    @js($googleMapsMapId);

/*
|--------------------------------------------------------------------------
| FORMAT DURATION
|--------------------------------------------------------------------------
*/

function formatMapDuration(
    milliseconds
)
{
    const totalMinutes =
        Math.round(
            Number(
                milliseconds || 0
            )
            /
            60000
        );

    const hours =
        Math.floor(
            totalMinutes / 60
        );

    const minutes =
        totalMinutes % 60;


    if (hours <= 0) {

        return `${minutes} menit`;
    }


    return (
        `${hours} jam `
        + `${minutes} menit`
    );
}


/*
|--------------------------------------------------------------------------
| INFO WINDOW
|--------------------------------------------------------------------------
*/

function buildMapInfoWindow(
    title,
    rows
)
{
    const wrapper =
        document.createElement(
            'div'
        );

    wrapper.style.minWidth =
        '230px';


    const heading =
        document.createElement(
            'strong'
        );

    heading.textContent =
        title || '-';

    wrapper.appendChild(
        heading
    );


    const details =
        document.createElement(
            'div'
        );

    details.style.marginTop =
        '8px';

    details.style.fontSize =
        '12px';

    details.style.lineHeight =
        '1.6';


    rows.forEach(
        ([label, value]) => {

            const row =
                document.createElement(
                    'div'
                );

            const labelElement =
                document.createElement(
                    'strong'
                );

            labelElement.textContent =
                `${label}: `;


            row.appendChild(
                labelElement
            );

            row.appendChild(
                document.createTextNode(
                    value ?? '-'
                )
            );

            details.appendChild(
                row
            );
        }
    );


    wrapper.appendChild(
        details
    );


    return wrapper;
}


/*
|--------------------------------------------------------------------------
| GOOGLE LIMIT:
|
| Origin + 25 intermediates + destination
| = 27 titik/request
|--------------------------------------------------------------------------
*/

function splitRoutePoints(
    points,
    maxPointsPerRequest = 27
)
{
    if (
        points.length
        <=
        maxPointsPerRequest
    ) {
        return [points];
    }


    const segments = [];

    let start = 0;


    while (
        start
        <
        points.length - 1
    ) {

        const end =
            Math.min(
                start
                +
                maxPointsPerRequest,

                points.length
            );


        const segment =
            points.slice(
                start,
                end
            );


        if (
            segment.length >= 2
        ) {

            segments.push(
                segment
            );
        }


        if (
            end
            >=
            points.length
        ) {
            break;
        }


        /*
         * Titik terakhir menjadi
         * titik awal segment selanjutnya.
         */
        start =
            end - 1;
    }


    return segments;
}


/*
|--------------------------------------------------------------------------
| INITIALIZE MAP
|--------------------------------------------------------------------------
*/

async function initializeDeliveryRouteMap()
{
    const mapElement =
        document.getElementById(
            'delivery-route-map'
        );


    const errorElement =
        document.getElementById(
            'route-map-error'
        );


    if (
        !mapElement

        ||

        mapElement.dataset.initialized
        ===
        '1'
    ) {
        return;
    }


    const data =
        window
            .__deliveryRouteMapData
        || {};


    const branch =
        data.branch || {};


    const stops =
        Array.isArray(
            data.stops
        )
            ? data.stops
            : [];


    const branchLat =
        Number(
            branch.latitude
        );


    const branchLng =
        Number(
            branch.longitude
        );


    if (
        !Number.isFinite(
            branchLat
        )

        ||

        !Number.isFinite(
            branchLng
        )
    ) {

        if (errorElement) {

            errorElement.hidden =
                false;

            errorElement.textContent =
                'Koordinat branch tidak valid.';
        }

        return;
    }


    try {

        await window.ensureGoogleMapsApi();

        if (
            !window.google
            ||
            !window.google.maps
            ||
            typeof window.google.maps.importLibrary !== 'function'
        ) {
            throw new Error(
                'Google Maps importLibrary() belum tersedia.'
            );
        }

        const {
            Map
        } =
            await google.maps
                .importLibrary(
                    'maps'
                );


        const {
            AdvancedMarkerElement,
            PinElement
        } =
            await google.maps
                .importLibrary(
                    'marker'
                );


        const {
            LatLngBounds
        } =
            await google.maps
                .importLibrary(
                    'core'
                );


        /*
         * Route Class baru Google.
         */
        const {
            Route
        } =
            await google.maps
                .importLibrary(
                    'routes'
                );


        mapElement.dataset.initialized =
            '1';


        const map =
            new Map(
                mapElement,
                {

                    center: {
                        lat:
                            branchLat,

                        lng:
                            branchLng,
                    },

                    zoom: 11,

                    mapId:
                        window.__routeOptimizationMapId
                        ||
                        'DEMO_MAP_ID',

                    mapTypeControl:
                        false,

                    streetViewControl:
                        false,
                }
            );


        const infoWindow =
            new google.maps
                .InfoWindow();


        const bounds =
            new LatLngBounds();


        const branchPosition = {

            lat:
                branchLat,

            lng:
                branchLng,
        };


        /*
        |--------------------------------------------------------------------------
        | DEPOT MARKER
        |--------------------------------------------------------------------------
        */

        const depotPin =
            new PinElement({

                glyphText:
                    'D',

                glyphColor:
                    '#ffffff',

                background:
                    '#2563eb',

                borderColor:
                    '#ffffff',

                scale:
                    1.15,
            });


        const depotMarker =
            new AdvancedMarkerElement({

                map,

                position:
                    branchPosition,

                title:
                    `${branch.code || 'DEPOT'} - ${branch.name || 'Branch'}`,

                content:
                    depotPin,

                gmpClickable:
                    true,
            });


        depotMarker.addListener(
            'gmp-click',
            () => {

                infoWindow.setContent(

                    buildMapInfoWindow(

                        `${branch.code || 'DEPOT'} - ${branch.name || 'Branch'}`,

                        [
                            [
                                'Tipe',
                                'Depot / Branch'
                            ],

                            [
                                'Route',
                                `#${data.route_id ?? '-'}`
                            ],
                        ]
                    )
                );


                infoWindow.open({

                    anchor:
                        depotMarker,

                    map,
                });
            }
        );


        bounds.extend(
            branchPosition
        );


        /*
        |--------------------------------------------------------------------------
        | STOP MARKERS
        |--------------------------------------------------------------------------
        */

        const validStops = [];


        stops.forEach(
            (stop) => {

                const lat =
                    Number(
                        stop
                            .store
                            ?.latitude
                    );


                const lng =
                    Number(
                        stop
                            .store
                            ?.longitude
                    );


                if (
                    !Number.isFinite(lat)

                    ||

                    !Number.isFinite(lng)
                ) {
                    return;
                }


                const position = {
                    lat,
                    lng,
                };


                validStops.push({

                    ...stop,

                    lat,
                    lng,
                });


                const pin =
                    new PinElement({

                        glyphText:
                            String(
                                stop.sequence
                            ),

                        glyphColor:
                            '#ffffff',

                        background:
                            '#f59e0b',

                        borderColor:
                            '#ffffff',

                        scale:
                            1.1,
                    });


                const marker =
                    new AdvancedMarkerElement({

                        map,

                        position,

                        title:
                            `${stop.sequence}. ${stop.store?.name || 'Store'}`,

                        content:
                            pin,

                        gmpClickable:
                            true,
                    });


                marker.addListener(
                    'gmp-click',
                    () => {

                        const packageSummary =
                            (
                                stop.packages
                                || []
                            )
                            .map(
                                (item) =>

                                    `${item.quantity} `
                                    + `${item.uom} `
                                    + `${item.product_name}`
                            )
                            .join(', ');


                        infoWindow.setContent(

                            buildMapInfoWindow(

                                `${stop.sequence}. ${stop.store?.name || 'Store'}`,

                                [
                                    [
                                        'Alamat',
                                        stop
                                            .store
                                            ?.address
                                        || '-'
                                    ],

                                    [
                                        'Arrival',
                                        stop
                                            .predicted_arrival
                                        || '-'
                                    ],

                                    [
                                        'Service',

                                        `${stop.service_start || '-'} - ${stop.service_end || '-'}`
                                    ],

                                    [
                                        'Package',
                                        packageSummary
                                        || '-'
                                    ],
                                ]
                            )
                        );


                        infoWindow.open({

                            anchor:
                                marker,

                            map,
                        });
                    }
                );


                bounds.extend(
                    position
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | TIDAK ADA STOP
        |--------------------------------------------------------------------------
        */

        if (
            validStops.length === 0
        ) {

            map.fitBounds(
                bounds,
                60
            );


            document
                .getElementById(
                    'route-map-distance'
                )
                .textContent =
                '-';


            document
                .getElementById(
                    'route-map-duration'
                )
                .textContent =
                '-';


            return;
        }


        /*
        |--------------------------------------------------------------------------
        | ROUTE POINTS
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Urutan berasal dari delivery_stops.sequence_order
        | hasil OR-Tools.
        |
        | Google TIDAK BOLEH reorder.
        |--------------------------------------------------------------------------
        */

        const routePoints = [

            {
                lat:
                    branchLat,

                lng:
                    branchLng,
            },

            ...validStops.map(
                (stop) => ({

                    lat:
                        stop.lat,

                    lng:
                        stop.lng,
                })
            ),

            {
                lat:
                    branchLat,

                lng:
                    branchLng,
            },
        ];


        const routeSegments =
            splitRoutePoints(
                routePoints,
                27
            );


        let totalDistanceMeters =
            0;


        let totalDurationMillis =
            0;


        /*
        |--------------------------------------------------------------------------
        | COMPUTE ROUTE
        |--------------------------------------------------------------------------
        */

        for (
            const segment
            of routeSegments
        ) {

            const intermediates =
                segment
                    .slice(
                        1,
                        -1
                    )
                    .map(
                        (point) => ({

                            location: {

                                lat:
                                    point.lat,

                                lng:
                                    point.lng,
                            },
                        })
                    );


            const request = {

                origin: {

                    lat:
                        segment[0].lat,

                    lng:
                        segment[0].lng,
                },


                destination: {

                    lat:
                        segment[
                            segment.length - 1
                        ].lat,

                    lng:
                        segment[
                            segment.length - 1
                        ].lng,
                },


                intermediates,


                travelMode:
                    'DRIVING',


                /*
                 * Sama dengan matrix optimizer:
                 * tidak menggunakan traffic realtime.
                 */
                routingPreference:
                    'TRAFFIC_UNAWARE',


                /*
                 * CRITICAL:
                 *
                 * Jangan ubah sequence
                 * hasil OR-Tools.
                 */
                optimizeWaypointOrder:
                    false,


                fields: [

                    'path',

                    'distanceMeters',

                    'durationMillis',

                    'viewport',
                ],
            };


            const result =
                await Route
                    .computeRoutes(
                        request
                    );


            const routeResult =
                result
                    .routes
                    ?.[0];


            if (!routeResult) {

                throw new Error(
                    'Google tidak mengembalikan route.'
                );
            }


            /*
             * Draw road polyline.
             */
            routeResult
                .createPolylines()
                .forEach(
                    (polyline) =>

                        polyline
                            .setMap(
                                map
                            )
                );


            /*
             * Fit viewport.
             */
            (
                routeResult.path
                || []
            )
            .forEach(
                (point) => {

                    bounds.extend(
                        point
                    );
                }
            );


            totalDistanceMeters +=
                Number(
                    routeResult
                        .distanceMeters
                    || 0
                );


            totalDurationMillis +=
                Number(
                    routeResult
                        .durationMillis
                    || 0
                );
        }


        map.fitBounds(
            bounds,
            55
        );


        /*
        |--------------------------------------------------------------------------
        | MAP SUMMARY
        |--------------------------------------------------------------------------
        */

        document
            .getElementById(
                'route-map-distance'
            )
            .textContent =

            `${(
                totalDistanceMeters
                /
                1000
            ).toLocaleString(
                'id-ID',
                {
                    minimumFractionDigits:
                        2,

                    maximumFractionDigits:
                        2,
                }
            )} km`;


        document
            .getElementById(
                'route-map-duration'
            )
            .textContent =

            formatMapDuration(
                totalDurationMillis
            );


    } catch (error) {

        console.error(
            '[DELIVERY ROUTE MAP]',
            error
        );


        mapElement
            .dataset
            .initialized =
            '0';


        if (errorElement) {

            errorElement.hidden =
                false;

            errorElement.textContent =
                error?.message
                ||
                'Gagal menampilkan visualisasi route.';
        }


        const distanceElement =
            document.getElementById(
                'route-map-distance'
            );


        const durationElement =
            document.getElementById(
                'route-map-duration'
            );


        if (distanceElement) {
            distanceElement.textContent =
                '-';
        }


        if (durationElement) {
            durationElement.textContent =
                '-';
        }
    }
}


/*
|--------------------------------------------------------------------------
| BOOT
|--------------------------------------------------------------------------
*/

function bootDeliveryRouteMap()
{
    const mapElement =
        document.getElementById(
            'delivery-route-map'
        );


    if (!mapElement) {
        return;
    }


    initializeDeliveryRouteMap();
}


if (
    document.readyState
    ===
    'loading'
) {

    document.addEventListener(

        'DOMContentLoaded',

        bootDeliveryRouteMap,

        {
            once: true
        }
    );

} else {

    bootDeliveryRouteMap();
}


/*
 * Mendukung navigasi Filament / Livewire.
 */
document.addEventListener(

    'livewire:navigated',

    bootDeliveryRouteMap
);

</script>

@endpush