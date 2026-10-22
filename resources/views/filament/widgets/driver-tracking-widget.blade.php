<x-filament-widgets::widget>

    <x-filament::section>

        <h2 style="
            font-size:20px;
            font-weight:700;
        ">
            TEST DRIVER TRACKING
        </h2>

        <p>
            Kalau tulisan ini muncul,
            berarti widget Dashboard sudah berhasil.
        </p>

    </x-filament::section>

    @include(
        'filament.partials.google-maps-loader'
    )

    <div class="dt-card">

        {{-- HEADER --}}
        <div class="dt-header">

            <div>
                <div class="dt-title-row">

                    <h2>
                        Live Driver Tracking
                    </h2>

                    <span class="dt-live-badge">
                        LIVE
                    </span>

                </div>

                <p>
                    Route approved hari ini dan
                    posisi terakhir driver yang
                    sedang menjalankan pengiriman.
                </p>
            </div>


            <div class="dt-header-summary">

                <div>
                    <strong>
                        {{
                            $trackingData[
                                'route_count'
                            ]
                            ?? 0
                        }}
                    </strong>

                    <span>
                        Approved Route
                    </span>
                </div>


                <div>
                    <strong id="dt-ongoing-count">
                        {{
                            $trackingData[
                                'ongoing_count'
                            ]
                            ?? 0
                        }}
                    </strong>

                    <span>
                        Ongoing
                    </span>
                </div>


                <div>
                    <strong>
                        {{
                            $trackingData[
                                'waiting_count'
                            ]
                            ?? 0
                        }}
                    </strong>

                    <span>
                        Waiting
                    </span>
                </div>


                @if (
                    $trackingData[
                        'branch'
                    ]
                    ?? null
                )

                    <div class="dt-branch">

                        <strong>
                            {{
                                $trackingData[
                                    'branch'
                                ]['code']
                                ?? '-'
                            }}
                        </strong>

                        <span>
                            {{
                                $trackingData[
                                    'branch'
                                ]['name']
                                ?? '-'
                            }}
                        </span>

                    </div>

                @endif

            </div>

        </div>


        {{-- MAP --}}
        <div class="dt-map-wrapper">

            <div
                id="dashboard-driver-tracking-map"
                class="dt-map"
            ></div>


            {{-- LEGEND --}}
            <div class="dt-legend">

                <div class="dt-legend-header">

                    <strong>
                        Route Hari Ini
                    </strong>

                    <span>
                        {{
                            \Carbon\Carbon::parse(
                                $trackingData[
                                    'route_date'
                                ]
                            )->format(
                                'd M Y'
                            )
                        }}
                    </span>

                </div>


                <div
                    id="dt-route-list"
                    class="dt-route-list"
                >

                    @forelse (
                        $trackingData[
                            'routes'
                        ]
                        ?? []
                        as $route
                    )

                        <button
                            type="button"
                            class="dt-route-item"
                            data-route-id="{{
                                $route['id']
                            }}"
                        >

                            <span
                                class="dt-route-color"
                                style="
                                    background:
                                    {{
                                        $route[
                                            'color'
                                        ]
                                    }};
                                "
                            ></span>


                            <span class="dt-route-info">

                                <strong>
                                    Route #{{
                                        $route[
                                            'id'
                                        ]
                                    }}
                                </strong>

                                <small>
                                    {{
                                        $route[
                                            'driver'
                                        ]['name']
                                        ?? '-'
                                    }}

                                    ·

                                    {{
                                        $route[
                                            'vehicle'
                                        ][
                                            'plate_number'
                                        ]
                                        ?? '-'
                                    }}
                                </small>

                            </span>


                            <span
                                class="
                                    dt-route-status
                                    dt-route-status-{{
                                        strtolower(
                                            $route[
                                                'status'
                                            ]
                                            ?? ''
                                        )
                                    }}
                                "
                            >
                                {{
                                    strtoupper(
                                        $route[
                                            'status'
                                        ]
                                        ?? '-'
                                    )
                                }}
                            </span>

                        </button>

                    @empty

                        <div class="dt-empty">
                            Belum ada route approved
                            hari ini.
                        </div>

                    @endforelse

                </div>

            </div>

        </div>


        {{-- FOOTER --}}
        <div class="dt-footer">

            <span>
                Posisi driver diperbarui
                setiap 5 menit.
            </span>

            <span id="dt-last-refresh">
                Belum ada refresh posisi.
            </span>

        </div>


        <div
            id="dt-error"
            class="dt-error"
            hidden
        ></div>

    </div>


    @push('styles')

        <style>

            .dt-card {
                overflow: hidden;
                border: 1px solid #e5e7eb;
                border-radius: 16px;
                background: #fff;
            }

            .dark .dt-card {
                background: #111827;
                border-color: #374151;
            }


            .dt-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                padding: 16px 20px;
                border-bottom: 1px solid #e5e7eb;
            }

            .dark .dt-header {
                border-color: #374151;
            }


            .dt-title-row {
                display: flex;
                align-items: center;
                gap: 8px;
            }


            .dt-title-row h2 {
                margin: 0;
                font-size: 17px;
                font-weight: 700;
            }


            .dt-live-badge {
                padding: 3px 7px;
                border-radius: 999px;
                background: #dcfce7;
                color: #166534;
                font-size: 9px;
                font-weight: 800;
            }


            .dt-header p {
                margin: 4px 0 0;
                color: #6b7280;
                font-size: 11px;
            }


            .dt-header-summary {
                display: flex;
                align-items: center;
                gap: 24px;
            }


            .dt-header-summary > div {
                text-align: right;
            }


            .dt-header-summary strong {
                display: block;
                font-size: 15px;
            }


            .dt-header-summary span {
                display: block;
                margin-top: 2px;
                color: #6b7280;
                font-size: 9px;
            }


            .dt-map-wrapper {
                position: relative;
            }


            .dt-map {
                width: 100%;
                height: 480px;
                background: #f3f4f6;
            }


            .dt-legend {
                position: absolute;
                top: 14px;
                right: 14px;
                z-index: 5;

                width: 300px;

                overflow: hidden;

                border:
                    1px solid
                    rgba(229, 231, 235, .95);

                border-radius: 13px;

                background:
                    rgba(255, 255, 255, .96);

                box-shadow:
                    0 8px 26px
                    rgba(0, 0, 0, .12);

                backdrop-filter:
                    blur(6px);
            }


            .dark .dt-legend {
                background:
                    rgba(17, 24, 39, .96);

                border-color:
                    rgba(55, 65, 81, .95);
            }


            .dt-legend-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;

                padding: 11px 12px;

                border-bottom:
                    1px solid #e5e7eb;
            }


            .dark .dt-legend-header {
                border-color: #374151;
            }


            .dt-legend-header strong {
                font-size: 11px;
            }


            .dt-legend-header span {
                color: #6b7280;
                font-size: 9px;
            }


            .dt-route-list {
                max-height: 310px;
                overflow-y: auto;
                padding: 7px;
            }


            .dt-route-item {
                width: 100%;

                display: grid;

                grid-template-columns:
                    9px
                    minmax(0, 1fr)
                    auto;

                align-items: center;

                gap: 8px;

                padding: 8px;

                border: 0;
                border-radius: 8px;

                background: transparent;

                text-align: left;

                cursor: pointer;
            }


            .dt-route-item:hover {
                background: #f9fafb;
            }


            .dark .dt-route-item:hover {
                background: #1f2937;
            }


            .dt-route-color {
                width: 9px;
                height: 9px;

                border-radius: 999px;
            }


            .dt-route-info {
                min-width: 0;
            }


            .dt-route-info strong,
            .dt-route-info small {
                display: block;
            }


            .dt-route-info strong {
                font-size: 11px;
            }


            .dt-route-info small {
                margin-top: 1px;

                overflow: hidden;

                color: #6b7280;

                font-size: 9px;

                text-overflow: ellipsis;
                white-space: nowrap;
            }


            .dt-route-status {
                padding: 2px 6px;

                border-radius: 999px;

                font-size: 8px;
                font-weight: 700;
            }


            .dt-route-status-planned {
                background: #fef3c7;
                color: #92400e;
            }


            .dt-route-status-ongoing {
                background: #dcfce7;
                color: #166534;
            }


            .dt-footer {
                min-height: 40px;

                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;

                padding: 8px 16px;

                border-top: 1px solid #e5e7eb;

                color: #6b7280;

                font-size: 10px;
            }


            .dark .dt-footer {
                border-color: #374151;
            }


            .dt-empty {
                padding: 20px;

                color: #6b7280;

                font-size: 11px;

                text-align: center;
            }


            .dt-error {
                padding: 10px 14px;

                border-top: 1px solid #fecaca;

                background: #fef2f2;

                color: #991b1b;

                font-size: 11px;
            }


            @media (
                max-width: 768px
            ) {

                .dt-header {
                    align-items: flex-start;
                    flex-direction: column;
                }


                .dt-header-summary {
                    width: 100%;

                    justify-content:
                        space-between;

                    gap: 12px;
                }


                .dt-header-summary > div {
                    text-align: left;
                }


                .dt-map {
                    height: 430px;
                }


                .dt-legend {
                    top: auto;
                    right: 10px;
                    bottom: 12px;
                    left: 10px;

                    width: auto;
                }


                .dt-route-list {
                    max-height: 150px;
                }
            }

        </style>

    @endpush


    @push('scripts')

        <script>

            window.__dashboardTrackingData =
                @js($trackingData);

            window.__dashboardTrackingKey =
                @js($googleMapsBrowserKey);

            window.__dashboardTrackingMapId =
                @js($googleMapsMapId);


            window.__dashboardTrackingState =
                window.__dashboardTrackingState
                || {
                    map: null,
                    infoWindow: null,
                    driverMarkers: {},
                    routeBounds: {},
                    refreshTimer: null,
                };


            /*
            |--------------------------------------------------------------------------
            | DRIVER POPUP
            |--------------------------------------------------------------------------
            */

            function dashboardDriverPopup(
                driver
            ) {

                const wrapper =
                    document.createElement(
                        'div'
                    );

                wrapper.style.minWidth =
                    '240px';


                const title =
                    document.createElement(
                        'strong'
                    );

                title.textContent =
                    driver.driver_name
                    || '-';


                wrapper.appendChild(
                    title
                );


                const rows = [

                    [
                        'Route',
                        `#${driver.route_id ?? '-'}`
                    ],

                    [
                        'Vehicle',
                        driver.vehicle_plate
                        || '-'
                    ],

                    [
                        'Last Update',
                        driver.recorded_at
                        || '-'
                    ],

                    [
                        'Speed',
                        driver.speed !== null
                        &&
                        driver.speed !== undefined

                            ? `${driver.speed} km/h`

                            : '-'
                    ],

                    [
                        'Accuracy',
                        driver.accuracy !== null
                        &&
                        driver.accuracy !== undefined

                            ? `${driver.accuracy} m`

                            : '-'
                    ],
                ];


                const detail =
                    document.createElement(
                        'div'
                    );

                detail.style.marginTop =
                    '8px';

                detail.style.fontSize =
                    '11px';

                detail.style.lineHeight =
                    '1.6';


                rows.forEach(
                    ([label, value]) => {

                        const row =
                            document.createElement(
                                'div'
                            );

                        const strong =
                            document.createElement(
                                'strong'
                            );

                        strong.textContent =
                            `${label}: `;


                        row.appendChild(
                            strong
                        );

                        row.appendChild(
                            document.createTextNode(
                                String(value)
                            )
                        );

                        detail.appendChild(
                            row
                        );
                    }
                );


                wrapper.appendChild(
                    detail
                );


                return wrapper;
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE DRIVER MARKERS
            |--------------------------------------------------------------------------
            */

            function updateDashboardDriverMarkers(
                drivers
            ) {

                const state =
                    window.__dashboardTrackingState;


                if (
                    !state.map
                    ||
                    !Array.isArray(
                        drivers
                    )
                ) {
                    return;
                }


                const visibleIds =
                    new Set();


                drivers.forEach(
                    driver => {

                        const driverId =
                            String(
                                driver.driver_id
                                ?? ''
                            );


                        const lat =
                            Number(
                                driver.latitude
                            );


                        const lng =
                            Number(
                                driver.longitude
                            );


                        if (
                            !driverId
                            ||
                            !Number.isFinite(
                                lat
                            )
                            ||
                            !Number.isFinite(
                                lng
                            )
                        ) {
                            return;
                        }


                        visibleIds.add(
                            driverId
                        );


                        const position = {
                            lat,
                            lng,
                        };


                        const existing =
                            state.driverMarkers[
                                driverId
                            ];


                        /*
                         * Marker sudah ada.
                         *
                         * Tidak recreate map.
                         * Tidak recreate marker.
                         */
                        if (existing) {

                            existing.driver =
                                driver;

                            existing.marker.position =
                                position;

                            return;
                        }


                        /*
                         * Marker baru.
                         */
                        const pin =
                            new google.maps.marker
                                .PinElement({

                                    glyphText:
                                        '🚚',

                                    glyphColor:
                                        '#ffffff',

                                    background:
                                        '#111827',

                                    borderColor:
                                        '#ffffff',

                                    scale:
                                        1.25,
                                });


                        const marker =
                            new google.maps.marker
                                .AdvancedMarkerElement({

                                    map:
                                        state.map,

                                    position,

                                    content:
                                        pin,

                                    title:
                                        `${driver.driver_name} - Route #${driver.route_id}`,

                                    gmpClickable:
                                        true,
                                });


                        state.driverMarkers[
                            driverId
                        ] = {
                            marker,
                            driver,
                        };


                        marker.addListener(
                            'gmp-click',
                            () => {

                                const latest =
                                    state.driverMarkers[
                                        driverId
                                    ];


                                if (!latest) {
                                    return;
                                }


                                state.infoWindow
                                    .setContent(
                                        dashboardDriverPopup(
                                            latest.driver
                                        )
                                    );


                                state.infoWindow
                                    .open({
                                        map:
                                            state.map,

                                        anchor:
                                            latest.marker,
                                    });
                            }
                        );
                    }
                );


                /*
                 * Driver sudah tidak ongoing.
                 *
                 * Marker dihapus.
                 */
                Object
                    .keys(
                        state.driverMarkers
                    )
                    .forEach(
                        driverId => {

                            if (
                                visibleIds.has(
                                    driverId
                                )
                            ) {
                                return;
                            }


                            state
                                .driverMarkers[
                                    driverId
                                ]
                                .marker
                                .map =
                                    null;


                            delete state
                                .driverMarkers[
                                    driverId
                                ];
                        }
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | FETCH LIVE LOCATION
            |--------------------------------------------------------------------------
            */

            async function refreshDashboardDriverLocations()
            {

                /*
                 * Tab browser tidak aktif.
                 *
                 * Skip request.
                 */
                if (document.hidden) {
                    return;
                }


                const data =
                    window.__dashboardTrackingData;


                if (!data?.route_date) {
                    return;
                }


                try {

                    const response =
                        await fetch(
                            `/admin/delivery-routes/driver-locations?route_date=${encodeURIComponent(data.route_date)}`,
                            {
                                headers: {
                                    'Accept':
                                        'application/json',
                                },

                                credentials:
                                    'same-origin',
                            }
                        );


                    if (!response.ok) {
                        throw new Error(
                            'Gagal mengambil posisi driver.'
                        );
                    }


                    const result =
                        await response.json();


                    updateDashboardDriverMarkers(
                        result.drivers
                        || []
                    );


                    const lastRefresh =
                        document.getElementById(
                            'dt-last-refresh'
                        );


                    if (lastRefresh) {

                        lastRefresh.textContent =
                            'Update posisi: '
                            +
                            new Date()
                                .toLocaleTimeString(
                                    'id-ID',
                                    {
                                        hour:
                                            '2-digit',

                                        minute:
                                            '2-digit',

                                        second:
                                            '2-digit',
                                    }
                                );
                    }

                } catch (error) {

                    console.error(
                        '[DASHBOARD TRACKING]',
                        error
                    );

                }
            }


            /*
            |--------------------------------------------------------------------------
            | INIT MAP
            |--------------------------------------------------------------------------
            */

            async function initDashboardDriverTracking()
            {

                const element =
                    document.getElementById(
                        'dashboard-driver-tracking-map'
                    );


                if (
                    !element
                    ||
                    element.dataset.initialized
                        === '1'
                ) {
                    return;
                }


                const data =
                    window.__dashboardTrackingData
                    || {};


                const branch =
                    data.branch
                    || {};


                const branchLat =
                    Number(
                        branch.latitude
                    );


                const branchLng =
                    Number(
                        branch.longitude
                    );


                try {

                    await window
                        .ensureGoogleMapsApi();


                    const [
                        { Map },
                        {
                            AdvancedMarkerElement,
                            PinElement,
                        },
                        {
                            LatLngBounds,
                        },
                    ] =
                        await Promise.all([

                            google.maps
                                .importLibrary(
                                    'maps'
                                ),

                            google.maps
                                .importLibrary(
                                    'marker'
                                ),

                            google.maps
                                .importLibrary(
                                    'core'
                                ),
                        ]);


                    /*
                     * expose ke namespace Google
                     * untuk refresh marker.
                     */
                    google.maps.marker =
                        google.maps.marker
                        || {};

                    google.maps.marker
                        .AdvancedMarkerElement =
                            AdvancedMarkerElement;

                    google.maps.marker
                        .PinElement =
                            PinElement;


                    const defaultCenter = {

                        lat:
                            Number.isFinite(
                                branchLat
                            )
                                ? branchLat
                                : -2.548926,

                        lng:
                            Number.isFinite(
                                branchLng
                            )
                                ? branchLng
                                : 118.0148634,
                    };


                    const map =
                        new Map(
                            element,
                            {
                                center:
                                    defaultCenter,

                                zoom:
                                    Number.isFinite(
                                        branchLat
                                    )
                                    &&
                                    Number.isFinite(
                                        branchLng
                                    )
                                        ? 11
                                        : 5,

                                mapId:
                                    window
                                        .__dashboardTrackingMapId

                                    ||
                                    'DEMO_MAP_ID',

                                mapTypeControl:
                                    false,

                                streetViewControl:
                                    false,
                            }
                        );


                    const state =
                        window
                            .__dashboardTrackingState;


                    state.map =
                        map;


                    state.infoWindow =
                        new google.maps
                            .InfoWindow();


                    element.dataset.initialized =
                        '1';


                    const bounds =
                        new LatLngBounds();


                    /*
                    |--------------------------------------------------------------------------
                    | BRANCH
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Number.isFinite(
                            branchLat
                        )
                        &&
                        Number.isFinite(
                            branchLng
                        )
                    ) {

                        const position = {
                            lat:
                                branchLat,

                            lng:
                                branchLng,
                        };


                        const pin =
                            new PinElement({

                                glyphText:
                                    'D',

                                glyphColor:
                                    '#ffffff',

                                background:
                                    '#111827',

                                borderColor:
                                    '#ffffff',
                            });


                        new AdvancedMarkerElement({

                            map,

                            position,

                            content:
                                pin,

                            title:
                                `${branch.code ?? 'DEPOT'} - ${branch.name ?? 'Branch'}`,
                        });


                        bounds.extend(
                            position
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | APPROVED ROUTES
                    |--------------------------------------------------------------------------
                    */

                    (
                        data.routes
                        || []
                    )
                        .forEach(
                            route => {

                                const color =
                                    route.color
                                    || '#2563eb';


                                const geometry =
                                    Array.isArray(
                                        route.geometry
                                    )
                                        ? route.geometry
                                        : [];


                                if (
                                    geometry.length
                                    < 2
                                ) {
                                    return;
                                }


                                new google.maps
                                    .Polyline({

                                        map,

                                        path:
                                            geometry,

                                        strokeColor:
                                            color,

                                        strokeOpacity:
                                            .85,

                                        strokeWeight:
                                            5,

                                        zIndex:
                                            10,
                                    });


                                state.routeBounds[
                                    String(
                                        route.id
                                    )
                                ] =
                                    geometry;


                                geometry.forEach(
                                    point => {

                                        bounds.extend(
                                            point
                                        );
                                    }
                                );
                            }
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | INITIAL FIT
                    |--------------------------------------------------------------------------
                    */

                    if (!bounds.isEmpty()) {

                        map.fitBounds(
                            bounds,
                            50
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | LOAD LIVE LOCATION PERTAMA
                    |--------------------------------------------------------------------------
                    */

                    await
                        refreshDashboardDriverLocations();


                    /*
                    |--------------------------------------------------------------------------
                    | POLLING 5 MENIT
                    |--------------------------------------------------------------------------
                    */

                    if (
                        state.refreshTimer
                    ) {

                        clearInterval(
                            state.refreshTimer
                        );
                    }


                    state.refreshTimer =
                        setInterval(
                            refreshDashboardDriverLocations,
                            5
                            *
                            60
                            *
                            1000
                        );

                } catch (error) {

                    console.error(
                        '[DASHBOARD DRIVER TRACKING]',
                        error
                    );


                    const errorElement =
                        document.getElementById(
                            'dt-error'
                        );


                    if (errorElement) {

                        errorElement.hidden =
                            false;

                        errorElement.textContent =
                            error?.message
                            ||
                            'Map live tracking gagal dimuat.';
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | CLICK ROUTE LEGEND
            |--------------------------------------------------------------------------
            */

            if (
                !window
                    .__dashboardRouteClickRegistered
            ) {

                window
                    .__dashboardRouteClickRegistered =
                        true;


                document.addEventListener(
                    'click',
                    event => {

                        const button =
                            event.target
                                instanceof Element

                                ? event.target
                                    .closest(
                                        '.dt-route-item'
                                    )

                                : null;


                        if (!button) {
                            return;
                        }


                        const routeId =
                            String(
                                button.dataset
                                    .routeId
                                || ''
                            );


                        const state =
                            window
                                .__dashboardTrackingState;


                        const geometry =
                            state.routeBounds[
                                routeId
                            ];


                        if (
                            !state.map
                            ||
                            !Array.isArray(
                                geometry
                            )
                            ||
                            geometry.length
                                === 0
                        ) {
                            return;
                        }


                        const bounds =
                            new google.maps
                                .LatLngBounds();


                        geometry.forEach(
                            point => {

                                bounds.extend(
                                    point
                                );
                            }
                        );


                        state.map.fitBounds(
                            bounds,
                            70
                        );
                    }
                );
            }


            /*
            |--------------------------------------------------------------------------
            | TAB ACTIVE
            |--------------------------------------------------------------------------
            */

            if (
                !window
                    .__dashboardTrackingVisibilityRegistered
            ) {

                window
                    .__dashboardTrackingVisibilityRegistered =
                        true;


                document.addEventListener(
                    'visibilitychange',
                    () => {

                        if (
                            !document.hidden
                        ) {

                            refreshDashboardDriverLocations();
                        }
                    }
                );
            }


            /*
            |--------------------------------------------------------------------------
            | FILAMENT NAVIGATION
            |--------------------------------------------------------------------------
            */

            document.addEventListener(
                'livewire:navigated',
                initDashboardDriverTracking
            );


            if (
                document.readyState
                === 'loading'
            ) {

                document.addEventListener(
                    'DOMContentLoaded',
                    initDashboardDriverTracking,
                    {
                        once:
                            true,
                    }
                );

            } else {

                initDashboardDriverTracking();
            }

        </script>

    @endpush

</x-filament-widgets::widget>