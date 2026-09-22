<x-filament-panels::page>
    
    <div
        wire:poll.30s="refreshTracking"
        aria-hidden="true"
        style="display:none;"
    ></div>

    <div class="tracking-map-panel">

        {{-- ==================================================
             HEADER
        =================================================== --}}

        <div class="tracking-map-header">

            <div>

                <h2>
                    Live Driver Tracking
                </h2>

                <p>
                    Posisi driver dan perjalanan aktual
                    berdasarkan data GPS yang diterima.
                </p>

            </div>


            <div class="tracking-header-right">

                <div class="tracking-driver-count">

                    <strong>
                        {{ count($trackingData['drivers']) }}
                    </strong>

                    <span>
                        Driver
                    </span>

                </div>


                <div class="tracking-branch">

                    @if ($trackingData['branch'])

                        <strong>
                            {{ $trackingData['branch']['code'] }}
                        </strong>

                        <span>
                            {{ $trackingData['branch']['name'] }}
                        </span>

                    @else

                        <span>
                            Branch tidak ditemukan
                        </span>

                    @endif

                </div>

            </div>

        </div>


        {{-- ==================================================
             MAP WRAPPER
        =================================================== --}}

        <div class="tracking-map-wrapper">

            <div
                id="driver-tracking-map"
                wire:ignore
                class="tracking-map"
            ></div>


            {{-- ==============================================
                 LEGENDA DRIVER
            =============================================== --}}

            <div class="tracking-legend">

                <div class="tracking-legend-header">

                    <div>

                        <strong>
                            Legenda Driver
                        </strong>

                        <span>
                            Klik driver untuk melihat posisi
                        </span>

                    </div>

                </div>


                <div class="tracking-legend-list">

                    @forelse ($trackingData['drivers'] as $driver)

                        <button
                            type="button"
                            class="
                                tracking-legend-item
                                {{ $driver['current_location'] ? '' : 'tracking-legend-disabled' }}
                            "
                            data-driver-id="{{ $driver['id'] }}"
                        >

                            <span
                                class="tracking-legend-dot"
                                style="
                                    background:
                                    {{ $driver['color'] }};
                                "
                            ></span>


                            <span class="tracking-legend-driver">

                                <strong>
                                    {{ $driver['name'] }}
                                </strong>

                                @if ($driver['plate_number'])

                                    <small>
                                        {{ $driver['plate_number'] }}
                                    </small>

                                @endif

                            </span>


                            <span
                                class="
                                    tracking-legend-status
                                    tracking-legend-status-{{
                                        strtolower(
                                            $driver['status']
                                        )
                                    }}
                                "
                            >

                                {{
                                    strtoupper(
                                        $driver['status']
                                    )
                                }}

                            </span>

                        </button>

                    @empty

                        <div class="tracking-legend-empty">

                            Belum ada driver.

                        </div>

                    @endforelse

                </div>

            </div>

        </div>


        <div
            id="driver-tracking-error"
            class="tracking-error"
            hidden
        ></div>

    </div>

</x-filament-panels::page>

@push('styles')

<style>

.tracking-map-panel {
    overflow: hidden;

    border:
        1px solid
        rgb(229 231 235);

    border-radius: 16px;

    background: white;
}

.dark .tracking-map-panel {
    background: rgb(17 24 39);
    border-color: rgb(55 65 81);
}


/* ==========================================================
   HEADER
========================================================== */

.tracking-map-header {
    min-height: 76px;

    padding:
        14px
        20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    border-bottom:
        1px solid
        rgb(229 231 235);
}

.dark .tracking-map-header {
    border-color: rgb(55 65 81);
}


.tracking-map-header h2 {
    margin: 0;

    font-size: 17px;
    font-weight: 700;

    color: rgb(17 24 39);
}

.dark .tracking-map-header h2 {
    color: rgb(243 244 246);
}


.tracking-map-header p {
    margin: 4px 0 0;

    color: rgb(107 114 128);

    font-size: 12px;
}


.tracking-header-right {
    display: flex;
    align-items: center;

    gap: 24px;
}


.tracking-driver-count {
    text-align: right;
}

.tracking-driver-count strong {
    display: block;

    font-size: 16px;
}

.tracking-driver-count span {
    display: block;

    margin-top: 1px;

    color: rgb(107 114 128);

    font-size: 10px;
}


.tracking-branch {
    min-width: 150px;

    text-align: right;
}

.tracking-branch strong {
    display: block;

    font-size: 13px;
}

.tracking-branch span {
    display: block;

    margin-top: 2px;

    color: rgb(107 114 128);

    font-size: 11px;
}


/* ==========================================================
   MAP
========================================================== */

.tracking-map-wrapper {
    position: relative;
}


.tracking-map {
    width: 100%;

    /*
     * Tinggi FIXED.
     *
     * Tidak mengikuti 100vh,
     * sehingga browser zoom tidak membuat
     * map semakin tinggi.
     */
    height: 620px;

    background: rgb(243 244 246);
}


/* ==========================================================
   FLOATING LEGEND
========================================================== */

.tracking-legend {
    position: absolute;

    z-index: 5;

    top: 14px;
    right: 14px;

    width: 290px;

    overflow: hidden;

    border:
        1px solid
        rgba(229, 231, 235, 0.95);

    border-radius: 14px;

    background:
        rgba(255, 255, 255, 0.96);

    box-shadow:
        0 8px 28px
        rgba(0, 0, 0, 0.12);

    backdrop-filter:
        blur(6px);
}


.tracking-legend-header {
    padding:
        12px
        14px;

    border-bottom:
        1px solid
        rgb(229 231 235);
}


.tracking-legend-header strong {
    display: block;

    font-size: 12px;
}


.tracking-legend-header span {
    display: block;

    margin-top: 2px;

    color: rgb(107 114 128);

    font-size: 9px;
}


.tracking-legend-list {
    max-height: 320px;

    overflow-y: auto;

    padding: 7px;
}


.tracking-legend-item {
    width: 100%;

    padding:
        8px
        7px;

    display: grid;

    grid-template-columns:
        10px
        minmax(0, 1fr)
        auto;

    align-items: center;

    gap: 8px;

    border: 0;
    border-radius: 8px;

    background: transparent;

    text-align: left;

    cursor: pointer;
}


.tracking-legend-item:hover {
    background: rgb(249 250 251);
}


.tracking-legend-disabled {
    cursor: default;

    opacity: 0.55;
}


.tracking-legend-disabled:hover {
    background: transparent;
}


.tracking-legend-dot {
    width: 9px;
    height: 9px;

    border-radius: 999px;
}


.tracking-legend-driver {
    min-width: 0;
}


.tracking-legend-driver strong {
    display: block;

    overflow: hidden;

    text-overflow: ellipsis;
    white-space: nowrap;

    color: rgb(17 24 39);

    font-size: 11px;
}


.tracking-legend-driver small {
    display: block;

    margin-top: 1px;

    color: rgb(107 114 128);

    font-size: 9px;
}


.tracking-legend-status {
    padding:
        2px
        6px;

    border-radius: 999px;

    background: rgb(243 244 246);

    color: rgb(75 85 99);

    font-size: 8px;
    font-weight: 700;
}


.tracking-legend-status-ready {
    background: rgb(220 252 231);
    color: rgb(22 101 52);
}


.tracking-legend-status-active {
    background: rgb(219 234 254);
    color: rgb(30 64 175);
}


.tracking-legend-status-inactive {
    background: rgb(243 244 246);
    color: rgb(107 114 128);
}


.tracking-legend-empty {
    padding: 16px;

    text-align: center;

    color: rgb(107 114 128);

    font-size: 11px;
}


/* ==========================================================
   ERROR
========================================================== */

.tracking-error {
    padding:
        12px
        16px;

    border-top:
        1px solid
        rgb(254 202 202);

    background:
        rgb(254 242 242);

    color:
        rgb(153 27 27);

    font-size: 12px;
}


/* ==========================================================
   DARK MODE
========================================================== */

.dark .tracking-legend {
    border-color:
        rgba(55, 65, 81, 0.95);

    background:
        rgba(17, 24, 39, 0.96);
}


.dark .tracking-legend-header {
    border-color: rgb(55 65 81);
}


.dark .tracking-legend-driver strong {
    color: rgb(243 244 246);
}


.dark .tracking-legend-item:hover {
    background: rgb(31 41 55);
}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 700px) {

    .tracking-map-header {
        align-items: flex-start;

        flex-direction: column;
    }


    .tracking-header-right {
        width: 100%;

        justify-content: space-between;
    }


    .tracking-branch,
    .tracking-driver-count {
        text-align: left;
    }


    .tracking-map {
        height: 450px;
    }


    .tracking-legend {
        top: auto;
        right: 10px;
        bottom: 24px;
        left: 10px;

        width: auto;
    }


    .tracking-legend-list {
        max-height: 160px;
    }

}

</style>

@endpush

@push('scripts')

<script>

window.__driverTrackingData =
    @js($trackingData);

window.__driverTrackingState =
    window.__driverTrackingState
    || {

        map:
            null,

        infoWindow:
            null,

        PinElement:
            null,

        AdvancedMarkerElement:
            null,

        currentDriverMarkers:
            {},

        activeDriverId:
            null,
    };

function escapeTrackingHtml(
    value
) {
    return String(
        value ?? ''
    )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}


function buildTrackingPopup(
    driver,
    point
) {
    const packages =
        (
            point.packages
            || []
        )
        .map(
            packageItem => {

                return `
                    <li>
                        ${escapeTrackingHtml(
                            packageItem.product_name
                        )}
                        -
                        ${escapeTrackingHtml(
                            packageItem.quantity
                        )}
                        ${escapeTrackingHtml(
                            packageItem.uom
                        )}
                    </li>
                `;
            }
        )
        .join('');


    const photo =
        point.photo_url
            ? `
                <div style="margin-top:10px;">
                    <img
                        src="${escapeTrackingHtml(
                            point.photo_url
                        )}"
                        style="
                            width:220px;
                            max-height:180px;
                            object-fit:cover;
                            border-radius:8px;
                        "
                    >
                </div>
            `
            : '';


    return `
        <div style="min-width:240px;">

            <strong>
                ${escapeTrackingHtml(
                    driver.name
                )}
            </strong>

            <div style="margin-top:8px;font-size:12px;">

                <div>
                    <strong>Event:</strong>
                    ${escapeTrackingHtml(
                        point.title
                    )}
                </div>

                <div>
                    <strong>Waktu:</strong>
                    ${escapeTrackingHtml(
                        point.recorded_at
                    )}
                </div>

                <div>
                    <strong>Store:</strong>
                    ${escapeTrackingHtml(
                        point.store_name
                        || '-'
                    )}
                </div>

                ${
                    packages
                        ? `
                            <div style="margin-top:8px;">
                                <strong>Package:</strong>
                                <ul>
                                    ${packages}
                                </ul>
                            </div>
                        `
                        : ''
                }

                ${photo}

            </div>

        </div>
    `;
}

function buildCurrentDriverPopup(
    driver
)
{
    const location =
        driver.current_location
        || {};


    const wrapper =
        document.createElement(
            'div'
        );


    wrapper.style.minWidth =
        '270px';


    /*
    |--------------------------------------------------------------------------
    | NAME
    |--------------------------------------------------------------------------
    */

    const title =
        document.createElement(
            'div'
        );


    title.style.fontSize =
        '15px';


    title.style.fontWeight =
        '700';


    title.textContent =
        driver.name
        || '-';


    wrapper.appendChild(
        title
    );


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    const status =
        document.createElement(
            'div'
        );


    status.style.marginTop =
        '3px';


    status.style.fontSize =
        '11px';


    status.style.fontWeight =
        '600';


    status.style.color =
        driver.color;


    status.textContent =
        String(
            driver.status
            || '-'
        ).toUpperCase();


    wrapper.appendChild(
        status
    );


    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    const details = [

        [
            'Vehicle',
            driver.plate_number
            || '-'
        ],

        [
            'Route',
            driver.route_id
                ? 'Assigned'
                : '-'
        ],

        [
            'Route Status',
            String(
                driver.route_status
                || '-'
            ).toUpperCase()
        ],

        [
            'Last Update',
            location.recorded_at
            || '-'
        ],

        [
            'Latitude',
            location.latitude
            ?? '-'
        ],

        [
            'Longitude',
            location.longitude
            ?? '-'
        ],
    ];


    const detailWrapper =
        document.createElement(
            'div'
        );


    detailWrapper.style.marginTop =
        '12px';


    details.forEach(
        (
            [
                label,
                value
            ]
        ) => {

            const row =
                document.createElement(
                    'div'
                );


            row.style.display =
                'grid';


            row.style.gridTemplateColumns =
                '95px 1fr';


            row.style.gap =
                '10px';


            row.style.marginBottom =
                '6px';


            row.style.fontSize =
                '11px';


            const labelElement =
                document.createElement(
                    'span'
                );


            labelElement.style.color =
                '#6b7280';


            labelElement.textContent =
                label;


            const valueElement =
                document.createElement(
                    'strong'
                );


            valueElement.textContent =
                String(
                    value
                );


            row.appendChild(
                labelElement
            );


            row.appendChild(
                valueElement
            );


            detailWrapper.appendChild(
                row
            );
        }
    );


    wrapper.appendChild(
        detailWrapper
    );


    return wrapper;
}

/*
|--------------------------------------------------------------------------
| DRIVER INITIALS
|--------------------------------------------------------------------------
*/

function getDriverInitials(
    driver
) {
    return String(
        driver.name
        || 'D'
    )
        .trim()
        .split(
            /\s+/
        )
        .slice(
            0,
            2
        )
        .map(
            word =>
                word
                    .charAt(0)
                    .toUpperCase()
        )
        .join('');
}


/*
|--------------------------------------------------------------------------
| CURRENT DRIVER POSITION
|--------------------------------------------------------------------------
*/

function getCurrentDriverPosition(
    driver
) {
    const location =
        driver.current_location;


    if (!location) {
        return null;
    }


    const lat =
        Number(
            location.latitude
        );


    const lng =
        Number(
            location.longitude
        );


    if (
        !Number.isFinite(lat)
        ||
        !Number.isFinite(lng)
    ) {
        return null;
    }


    return {
        lat,
        lng,
    };
}


/*
|--------------------------------------------------------------------------
| CREATE / UPDATE CURRENT DRIVER MARKER
|--------------------------------------------------------------------------
|
| Jika marker belum ada:
|     buat marker.
|
| Jika marker sudah ada:
|     cukup ubah position.
|
| Google Map TIDAK dibuat ulang.
|--------------------------------------------------------------------------
*/

function createOrUpdateCurrentDriverMarker(
    driver
) {
    const state =
        window.__driverTrackingState;


    if (
        !state
        ||
        !state.map
        ||
        !state.PinElement
        ||
        !state.AdvancedMarkerElement
    ) {
        return null;
    }


    const driverId =
        String(
            driver.id
            ?? ''
        );


    if (!driverId) {
        return null;
    }


    const position =
        getCurrentDriverPosition(
            driver
        );


    const existing =
        state
            .currentDriverMarkers[
                driverId
            ];


    /*
    |--------------------------------------------------------------------------
    | DRIVER TIDAK PUNYA POSISI
    |--------------------------------------------------------------------------
    */

    if (!position) {

        if (existing) {

            existing.marker.map =
                null;


            delete state
                .currentDriverMarkers[
                    driverId
                ];
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | MARKER SUDAH ADA
    |--------------------------------------------------------------------------
    |
    | Hanya pindahkan marker.
    |--------------------------------------------------------------------------
    */

    if (existing) {

        existing.driver =
            driver;


        existing.marker.position =
            position;


        existing.marker.title =
            `Posisi terakhir ${driver.name}`;


        return position;
    }


    /*
    |--------------------------------------------------------------------------
    | MARKER BARU
    |--------------------------------------------------------------------------
    */

    const pin =
        new state.PinElement({

            glyphText:
                getDriverInitials(
                    driver
                ),

            background:
                driver.color
                || '#2563eb',

            glyphColor:
                '#ffffff',

            scale:
                1.3,
        });


    const marker =
        new state
            .AdvancedMarkerElement({

                map:
                    state.map,

                position,

                title:
                    `Posisi terakhir ${driver.name}`,

                content:
                    pin,

                gmpClickable:
                    true,
            });


    state.currentDriverMarkers[
        driverId
    ] = {

        marker,

        driver,
    };


    /*
    |--------------------------------------------------------------------------
    | CLICK CURRENT DRIVER PIN
    |--------------------------------------------------------------------------
    */

    marker.addListener(
        'gmp-click',
        () => {

            const latestEntry =
                window
                    .__driverTrackingState
                    .currentDriverMarkers[
                        driverId
                    ];


            if (!latestEntry) {
                return;
            }


            state.activeDriverId =
                driverId;


            state.infoWindow
                .setContent(
                    buildCurrentDriverPopup(
                        latestEntry.driver
                    )
                );


            state.infoWindow.open({

                map:
                    state.map,

                anchor:
                    latestEntry.marker,
            });
        }
    );


    return position;
}

/*
|--------------------------------------------------------------------------
| UPDATE LEGEND TANPA LIVEWIRE RERENDER
|--------------------------------------------------------------------------
*/

function updateDriverTrackingLegend(
    driver
) {
    const driverId =
        String(
            driver.id
            || ''
        );


    if (!driverId) {
        return;
    }


    const buttons =
        document.querySelectorAll(
            '.tracking-legend-item'
        );


    const button =
        Array
            .from(buttons)
            .find(
                item =>
                    String(
                        item.dataset.driverId
                        || ''
                    )
                    ===
                    driverId
            );


    if (!button) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | ENABLE / DISABLE
    |--------------------------------------------------------------------------
    */

    button.classList.toggle(
        'tracking-legend-disabled',
        !driver.current_location
    );


    /*
    |--------------------------------------------------------------------------
    | PLATE NUMBER
    |--------------------------------------------------------------------------
    */

    const driverWrapper =
        button.querySelector(
            '.tracking-legend-driver'
        );


    if (driverWrapper) {

        let plate =
            driverWrapper.querySelector(
                'small'
            );


        if (
            driver.plate_number
        ) {

            if (!plate) {

                plate =
                    document.createElement(
                        'small'
                    );

                driverWrapper.appendChild(
                    plate
                );
            }


            plate.textContent =
                driver.plate_number;

        } else if (plate) {

            plate.remove();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    const statusElement =
        button.querySelector(
            '.tracking-legend-status'
        );


    if (statusElement) {

        const status =
            String(
                driver.status
                || '-'
            );


        statusElement.className =
            'tracking-legend-status '
            +
            `tracking-legend-status-${status.toLowerCase()}`;


        statusElement.textContent =
            status.toUpperCase();
    }
}



function updateDriverTrackingPins(
    driverUpdates
) {
    if (
        !Array.isArray(
            driverUpdates
        )
    ) {
        return;
    }


    const state =
        window.__driverTrackingState;


    const data =
        window.__driverTrackingData
        || {
            drivers: []
        };


    data.drivers =
        data.drivers
        || [];


    /*
    |--------------------------------------------------------------------------
    | MERGE LIVE DATA
    |--------------------------------------------------------------------------
    |
    | points TIDAK ditimpa.
    |
    | Milestone geometry yang sudah digambar tetap hidup.
    |
    */

    const mergedDrivers = [];


    for (
        const update
        of driverUpdates
    ) {

        const existing =
            data.drivers.find(
                driver =>
                    String(
                        driver.id
                    )
                    ===
                    String(
                        update.id
                    )
            );


        let driver;


        if (existing) {

            /*
             * Preserve points.
             */
            const points =
                existing.points
                || [];


            Object.assign(
                existing,
                update
            );


            existing.points =
                points;


            driver =
                existing;

        } else {

            driver = {
                ...update,
                points: [],
            };


            data.drivers.push(
                driver
            );
        }


        mergedDrivers.push(
            driver
        );


        updateDriverTrackingLegend(
            driver
        );
    }


    window.__driverTrackingData =
        data;


    if (
        !state
        ||
        !state.map
    ) {
        return;
    }


    const visibleDriverIds =
        new Set();


    /*
    |--------------------------------------------------------------------------
    | UPDATE CURRENT MARKER
    |--------------------------------------------------------------------------
    */

    for (
        const driver
        of mergedDrivers
    ) {

        const position =
            createOrUpdateCurrentDriverMarker(
                driver
            );


        if (position) {

            visibleDriverIds.add(
                String(
                    driver.id
                )
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE MARKER TANPA POSISI
    |--------------------------------------------------------------------------
    */

    Object
        .keys(
            state.currentDriverMarkers
        )
        .forEach(
            driverId => {

                if (
                    visibleDriverIds.has(
                        driverId
                    )
                ) {

                    return;
                }


                const entry =
                    state
                        .currentDriverMarkers[
                            driverId
                        ];


                if (
                    entry
                    &&
                    entry.marker
                ) {

                    entry.marker.map =
                        null;
                }


                delete state
                    .currentDriverMarkers[
                        driverId
                    ];
            }
        );


    /*
    |--------------------------------------------------------------------------
    | UPDATE POPUP YANG SEDANG TERBUKA
    |--------------------------------------------------------------------------
    */

    if (
        state.activeDriverId
        &&
        state.currentDriverMarkers[
            state.activeDriverId
        ]
    ) {

        const entry =
            state.currentDriverMarkers[
                state.activeDriverId
            ];


        state.infoWindow
            .setContent(
                buildCurrentDriverPopup(
                    entry.driver
                )
            );
    }
}

/*
|--------------------------------------------------------------------------
| LIVE PIN UPDATE
|--------------------------------------------------------------------------
|
| Dipanggil setelah Livewire menerima trackingData terbaru.
|--------------------------------------------------------------------------
*/

function splitTrackingPoints(
    points,
    maxPoints = 27
) {
    if (
        points.length
        <= maxPoints
    ) {
        return [
            points
        ];
    }


    const result = [];

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
                maxPoints,
                points.length
            );


        result.push(
            points.slice(
                start,
                end
            )
        );


        if (
            end
            >=
            points.length
        ) {
            break;
        }


        start =
            end - 1;
    }


    return result;
}


async function initDriverTrackingMap()
{
    const element =
        document.getElementById(
            'driver-tracking-map'
        );


    if (
        !element
        ||
        element.dataset.initialized
        ===
        '1'
    ) {
        return;
    }


    try {

        await window
            .ensureGoogleMapsApi();


        const [
            {
                Map
            },

            {
                AdvancedMarkerElement,
                PinElement
            },

            {
                Route
            }

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
                        'routes'
                    ),
            ]);


        const data =
            window
                .__driverTrackingData
            || {};


        const branch =
            data.branch;


        /*
        |--------------------------------------------------------------------------
        | DEFAULT MAP CENTER
        |--------------------------------------------------------------------------
        |
        | Map tetap tampil walaupun:
        |
        | - belum ada Branch
        | - Branch belum punya latitude / longitude
        |
        | Default diarahkan ke tengah Indonesia.
        |
        */

        const defaultMapCenter = {

            lat: -2.548926,

            lng: 118.0148634,
        };


        const branchLatitude =
            branch?.latitude !== null
            &&
            branch?.latitude !== undefined

                ? Number(
                    branch.latitude
                )

                : null;


        const branchLongitude =
            branch?.longitude !== null
            &&
            branch?.longitude !== undefined

                ? Number(
                    branch.longitude
                )

                : null;


        const hasValidBranchPosition =

            branch

            &&

            Number.isFinite(
                branchLatitude
            )

            &&

            Number.isFinite(
                branchLongitude
            );


        const initialMapCenter =

            hasValidBranchPosition

                ? {

                    lat:
                        branchLatitude,

                    lng:
                        branchLongitude,
                }

                : defaultMapCenter;


        const map =
            new Map(
                element,
                {
                    center:
                        initialMapCenter,

                    zoom:
                        hasValidBranchPosition
                            ? 11
                            : 5,

                    mapId:
                        window
                            .__routeOptimizationMapId
                        ||
                        'DEMO_MAP_ID',
                }
            );

        window.__driverTrackingMap =
            map;


        element.dataset.initialized =
            '1';


        const infoWindow =
            new google.maps
                .InfoWindow();

        /*
        |--------------------------------------------------------------------------
        |--------------------------------------------------------------------------
        | POSISI TERKINI DRIVER
        |--------------------------------------------------------------------------
        |
        | Digunakan agar legenda bisa mencari marker
        | berdasarkan driver ID.
        |
        */
        
        const trackingState =
            window.__driverTrackingState;


        trackingState.map =
            map;


        trackingState.infoWindow =
            infoWindow;


        trackingState.PinElement =
            PinElement;


        trackingState.AdvancedMarkerElement =
            AdvancedMarkerElement;


        /*
         * Map baru = marker state baru.
         */
        trackingState.currentDriverMarkers =
            {};


        trackingState.activeDriverId =
            null;

        const bounds =
            new google.maps
                .LatLngBounds();

        /*
        |--------------------------------------------------------------------------
        | BRANCH MARKER
        |--------------------------------------------------------------------------
        |
        | Marker Branch hanya dibuat kalau:
        |
        | - Branch sudah ada
        | - latitude valid
        | - longitude valid
        |
        */

        if (
            hasValidBranchPosition
        ) {

            const branchPosition = {

                lat:
                    branchLatitude,

                lng:
                    branchLongitude,
            };


            const branchPin =
                new PinElement({

                    glyphText:
                        'B',

                    background:
                        '#2563eb',

                    glyphColor:
                        '#ffffff',
                });


            new AdvancedMarkerElement({

                map,

                position:
                    branchPosition,

                title:
                    `${branch.code} - ${branch.name}`,

                content:
                    branchPin,
            });

            bounds.extend(
                branchPosition
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DRIVERS
        |--------------------------------------------------------------------------
        */

        for (
            const driver
            of
            (
                data.drivers
                || []
            )
        ) {

            const points =
                driver.points
                || [];

            /*
            |--------------------------------------------------------------------------
            | EVENT MARKERS
            |--------------------------------------------------------------------------
            */

            points.forEach(
                (
                    point,
                    index
                ) => {

                    const position = {

                        lat:
                            Number(
                                point.latitude
                            ),

                        lng:
                            Number(
                                point.longitude
                            ),
                    };


                    if (
                        !Number.isFinite(
                            position.lat
                        )
                        ||
                        !Number.isFinite(
                            position.lng
                        )
                    ) {
                        return;
                    }

                    const pin =
                        new PinElement({

                            glyphText:
                                point.type
                                ===
                                'checkin'

                                    ? 'A'

                                    : String(
                                        index
                                    ),

                            background:
                                driver.color,

                            glyphColor:
                                '#ffffff',
                        });

                    const marker =
                        new AdvancedMarkerElement({

                            map,

                            position,

                            title:
                                `${driver.name} - ${point.title}`,

                            content:
                                pin,

                            gmpClickable:
                                true,
                        });

                    marker.addListener(
                        'gmp-click',
                        () => {

                            infoWindow
                                .setContent(
                                    buildTrackingPopup(
                                        driver,
                                        point
                                    )
                                );

                            infoWindow.open({

                                map,

                                anchor:
                                    marker,
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
            | ROUTE AKTUAL BERDASARKAN MILESTONE
            |--------------------------------------------------------------------------
            |
            | Check-in → arrival → checkpoint → arrival ...
            |
            | Google hanya menggambar jalan antar titik.
            |--------------------------------------------------------------------------
            */

            const validPoints =
                points
                    .map(
                        point => ({

                            lat:
                                Number(
                                    point.latitude
                                ),

                            lng:
                                Number(
                                    point.longitude
                                ),
                        })
                    )

                    .filter(
                        point =>
                            Number.isFinite(
                                point.lat
                            )
                            &&
                            Number.isFinite(
                                point.lng
                            )
                    );


            if (
                validPoints.length
                >=
                2
            ) {

                const segments =
                    splitTrackingPoints(
                        validPoints
                    );


                for (
                    const segment
                    of segments
                ) {

                    const {
                        routes
                    } =
                        await Route
                            .computeRoutes({

                                origin:
                                    segment[
                                        0
                                    ],

                                destination:
                                    segment[
                                        segment.length
                                        -
                                        1
                                    ],

                                intermediates:
                                    segment
                                        .slice(
                                            1,
                                            -1
                                        )
                                        .map(
                                            point => ({

                                                location:
                                                    point,
                                            })
                                        ),

                                travelMode:
                                    'DRIVING',

                                routingPreference:
                                    'TRAFFIC_UNAWARE',

                                optimizeWaypointOrder:
                                    false,

                                fields: [
                                    'path'
                                ],
                            });


                    const result =
                        routes?.[0];


                    if (!result) {
                        continue;
                    }


                    const polylines =
                        result
                            .createPolylines({

                                polylineOptions: {

                                    strokeColor:
                                        driver.color,

                                    strokeOpacity:
                                        0.85,

                                    strokeWeight:
                                        5,
                                },
                            });


                    polylines.forEach(
                        polyline => {

                            polyline.setMap(
                                map
                            );
                        }
                    );
                }
            }



            /*
            |--------------------------------------------------------------------------
            | POSISI TERKINI DRIVER
            |--------------------------------------------------------------------------
            */
            const currentPosition =
                createOrUpdateCurrentDriverMarker(
                    driver
                );


            if (currentPosition) {

                bounds.extend(
                    currentPosition
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CLICK LEGENDA DRIVER
        |--------------------------------------------------------------------------
        |
        | Klik nama driver:
        |
        | 1. Cari marker current location
        | 2. Pan map ke lokasi driver
        | 3. Zoom
        | 4. Buka detail popup
        |
        */

        if (
            !bounds.isEmpty()
        ) {

            map.fitBounds(
                bounds,
                50
            );
        }
    } catch (
        error
    ) {

        console.error(
            '[DRIVER TRACKING]',
            error
        );


        const errorElement =
            document.getElementById(
                'driver-tracking-error'
            );


        if (errorElement) {

            errorElement.classList
                .remove(
                    'hidden'
                );


            errorElement.textContent =
                error?.message
                ||
                'Gagal menampilkan Driver Tracking.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| GLOBAL LEGEND CLICK HANDLER
|--------------------------------------------------------------------------
|
| Event delegation.
|
| Jadi tetap bekerja walaupun isi legend dirender ulang
| oleh Livewire setiap 10 detik.
|--------------------------------------------------------------------------
*/

if (
    !window.__driverTrackingLegendHandlerRegistered
) {

    window.__driverTrackingLegendHandlerRegistered =
        true;


    document.addEventListener(
        'click',
        event => {

            const target =
                event.target
                instanceof Element

                    ? event.target

                    : null;


            if (!target) {
                return;
            }


            const button =
                target.closest(
                    '.tracking-legend-item'
                );


            if (!button) {
                return;
            }


            const driverId =
                String(
                    button
                        .dataset
                        .driverId
                    || ''
                );


            const state =
                window.__driverTrackingState;


            const entry =
                state
                    ?.currentDriverMarkers
                    ?.[driverId];


            if (!entry) {
                return;
            }


            const position =
                getCurrentDriverPosition(
                    entry.driver
                );


            if (!position) {
                return;
            }


            /*
             * Simpan driver popup aktif.
             */
            state.activeDriverId =
                driverId;


            /*
             * Fokus posisi driver.
             */
            state.map.panTo(
                position
            );


            state.map.setZoom(
                16
            );


            /*
             * Buka detail.
             */
            state.infoWindow
                .setContent(
                    buildCurrentDriverPopup(
                        entry.driver
                    )
                );


            state.infoWindow.open({

                map:
                    state.map,

                anchor:
                    entry.marker,
            });
        }
    );
}

/*
|--------------------------------------------------------------------------
| LIVEWIRE LIVE TRACKING
|--------------------------------------------------------------------------
*/

function registerDriverTrackingLivewireListener()
{
    if (
        !window.Livewire
        ||
        window
            .__driverTrackingLivewireListenerRegistered
    ) {
        return;
    }


    window
        .__driverTrackingLivewireListenerRegistered =
        true;


    Livewire.on(
        'driver-tracking-updated',
        event => {

            const driverUpdates =
                event?.driverUpdates
                ??
                event?.detail
                    ?.driverUpdates
                ??
                null;


            if (
                !Array.isArray(
                    driverUpdates
                )
            ) {
                return;
            }


            updateDriverTrackingPins(
                driverUpdates
            );
        }
    );
}

/*
 * Kalau Livewire sudah tersedia.
 */
if (window.Livewire) {

    registerDriverTrackingLivewireListener();

} else {

    /*
     * Kalau script page dieksekusi
     * sebelum Livewire siap.
     */
    document.addEventListener(
        'livewire:init',
        registerDriverTrackingLivewireListener,
        {
            once:
                true,
        }
    );
}

if (
    document.readyState
    ===
    'loading'
) {

    document.addEventListener(

        'DOMContentLoaded',

        initDriverTrackingMap,

        {
            once: true
        }
    );

} else {

    initDriverTrackingMap();
}


document.addEventListener(
    'livewire:navigated',
    () => {

        registerDriverTrackingLivewireListener();

        initDriverTrackingMap();
    }
);

</script>

@endpush