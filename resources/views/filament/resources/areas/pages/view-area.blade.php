<x-filament-panels::page>

    <div class="area-summary">

        <div>

            <span class="area-summary-label">
                Area
            </span>

            <strong>
                {{ $area->code }}
            </strong>

        </div>


        <div>

            <span class="area-summary-label">
                Cabang
            </span>

            <strong>
                {{
                    $area->branch?->init_cab
                    ?? '-'
                }}
                -
                {{
                    $area->branch?->name
                    ?? '-'
                }}
            </strong>

        </div>


        <div>

            <span class="area-summary-label">
                Jumlah Store
            </span>

            <strong>
                {{ count($stores) }} Store
            </strong>

        </div>


        <div>

            <span class="area-summary-label">
                Pin Valid
            </span>

            <strong>
                {{ count($mapStores) }} Pin
            </strong>

        </div>

    </div>



    <div class="area-detail-grid">


        {{-- =====================================================
             MAP
        ====================================================== --}}

        <section class="area-panel area-map-panel">

            <div class="area-panel-header">

                <div>

                    <h2>
                        Visualisasi Anggota Area
                    </h2>

                    <p>
                        Map menampilkan Store yang menjadi
                        anggota Area {{ $area->code }}.
                    </p>

                </div>


                <span class="area-code-badge">
                    {{ $area->code }}
                </span>

            </div>


            @if (blank($googleMapsBrowserKey))

                <div class="area-alert">
                    Google Maps API Key belum dikonfigurasi.
                </div>

            @endif


            @if (count($mapStores) > 0)

                <div
                    id="area-store-map"
                    wire:ignore
                    class="area-map"
                ></div>

            @else

                <div class="area-map-empty">

                    <div class="area-map-empty-icon">
                        !
                    </div>

                    <strong>
                        Belum ada Store dengan koordinat valid
                    </strong>

                    <span>
                        Store harus mempunyai latitude dan longitude
                        agar dapat ditampilkan pada map.
                    </span>

                </div>

            @endif

        </section>



        {{-- =====================================================
             STORE MEMBERS
        ====================================================== --}}

        <section class="area-panel">

            <div class="area-panel-header">

                <div>

                    <h2>
                        Anggota Area
                    </h2>

                    <p>
                        Daftar Store dengan Area
                        {{ $area->code }}.
                    </p>

                </div>

            </div>


            <div class="area-store-list">

                @forelse ($stores as $store)

                    <div
                        class="area-store-card"
                        data-store-id="{{ $store['id'] }}"
                    >

                        <div class="area-store-number">
                            {{ $loop->iteration }}
                        </div>


                        <div class="area-store-content">

                            <div class="area-store-head">

                                <div>

                                    <strong>
                                        {{ $store['name'] }}
                                    </strong>

                                    <span>
                                        {{ $store['code'] }}
                                    </span>

                                </div>


                                @if (
                                    $store['latitude'] !== null
                                    &&
                                    $store['longitude'] !== null
                                )

                                    <span class="area-coordinate-ok">
                                        PIN
                                    </span>

                                @else

                                    <span class="area-coordinate-missing">
                                        NO GPS
                                    </span>

                                @endif

                            </div>


                            <div class="area-store-address">

                                {{
                                    $store['address']
                                    ?: '-'
                                }}

                            </div>


                            <div class="area-store-hours">

                                <div>

                                    <span>
                                        Buka
                                    </span>

                                    <strong>
                                        {{
                                            $store['opening_time']
                                            ?: '-'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Tutup
                                    </span>

                                    <strong>
                                        {{
                                            $store['closing_time']
                                            ?: '-'
                                        }}
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="area-member-empty">

                        <strong>
                            Belum ada anggota Area
                        </strong>

                        <span>
                            Belum ada Store yang memiliki
                            area_id Area {{ $area->code }}.
                        </span>

                    </div>

                @endforelse

            </div>

        </section>

    </div>

</x-filament-panels::page>

@push('styles')

    <style>

        /* ============================================================
           SUMMARY
        ============================================================ */

        .area-summary {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }


        .area-summary > div {
            padding: 14px 16px;
            border: 1px solid rgb(229 231 235);
            border-radius: 12px;
            background: white;
        }


        .area-summary-label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            color: rgb(107 114 128);
        }


        .area-summary strong {
            font-size: 14px;
        }



        /* ============================================================
           MAIN
        ============================================================ */

        .area-detail-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.6fr)
                minmax(330px, .9fr);
            gap: 16px;
            align-items: start;
        }


        .area-panel {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 14px;
            background: white;
        }


        .area-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 16px;
            border-bottom: 1px solid rgb(229 231 235);
        }


        .area-panel-header h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }


        .area-panel-header p {
            margin: 3px 0 0;
            font-size: 11px;
            color: rgb(107 114 128);
        }


        .area-code-badge {
            display: inline-flex;
            padding: 5px 9px;
            border-radius: 999px;
            background: rgb(239 246 255);
            color: rgb(37 99 235);
            font-size: 11px;
            font-weight: 700;
        }



        /* ============================================================
           MAP
        ============================================================ */

        .area-map {
            width: 100%;
            height: 570px;
        }


        .area-alert {
            padding: 10px 16px;
            background: rgb(254 252 232);
            border-bottom: 1px solid rgb(253 224 71);
            color: rgb(133 77 14);
            font-size: 11px;
        }


        .area-map-empty {
            min-height: 420px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }


        .area-map-empty-icon {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgb(254 243 199);
            color: rgb(180 83 9);
            font-weight: 800;
        }


        .area-map-empty strong {
            font-size: 14px;
        }


        .area-map-empty span {
            margin-top: 5px;
            max-width: 380px;
            font-size: 11px;
            color: rgb(107 114 128);
        }



        /* ============================================================
           STORE LIST
        ============================================================ */

        .area-store-list {
            max-height: 570px;
            overflow-y: auto;
            padding: 8px;
        }


        .area-store-card {
            display: flex;
            gap: 10px;
            padding: 12px 10px;
            border-radius: 10px;
            transition:
                background .15s ease,
                box-shadow .15s ease;
        }


        .area-store-card + .area-store-card {
            border-top: 1px solid rgb(243 244 246);
        }


        .area-store-card:hover {
            background: rgb(249 250 251);
        }


        .area-store-number {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgb(245 158 11);
            color: white;
            font-size: 11px;
            font-weight: 800;
        }


        .area-store-content {
            flex: 1;
            min-width: 0;
        }


        .area-store-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
        }


        .area-store-head strong {
            display: block;
            font-size: 13px;
        }


        .area-store-head > div > span {
            display: block;
            margin-top: 2px;
            color: rgb(37 99 235);
            font-size: 10px;
            font-weight: 600;
        }


        .area-coordinate-ok,
        .area-coordinate-missing {
            flex-shrink: 0;
            padding: 3px 6px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
        }


        .area-coordinate-ok {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }


        .area-coordinate-missing {
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }


        .area-store-address {
            margin-top: 7px;
            font-size: 11px;
            line-height: 1.45;
            color: rgb(107 114 128);
        }


        .area-store-hours {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 6px;
            margin-top: 8px;
        }


        .area-store-hours > div {
            padding: 6px 8px;
            border-radius: 7px;
            background: rgb(249 250 251);
        }


        .area-store-hours span {
            display: block;
            font-size: 9px;
            color: rgb(107 114 128);
        }


        .area-store-hours strong {
            display: block;
            margin-top: 1px;
            font-size: 10px;
        }


        .area-member-empty {
            min-height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
        }


        .area-member-empty strong {
            font-size: 13px;
        }


        .area-member-empty span {
            margin-top: 5px;
            font-size: 11px;
            color: rgb(107 114 128);
        }



        /* ============================================================
           GOOGLE INFO WINDOW
        ============================================================ */

        .area-map-info {
            min-width: 220px;
            max-width: 300px;
            padding: 3px;
            font-family: Arial, sans-serif;
        }


        .area-map-info-title {
            font-size: 13px;
            font-weight: 700;
        }


        .area-map-info-code {
            margin-top: 2px;
            font-size: 10px;
            color: #2563eb;
        }


        .area-map-info-address {
            margin-top: 7px;
            font-size: 11px;
            line-height: 1.4;
        }


        .area-map-info-hours {
            margin-top: 7px;
            font-size: 10px;
            color: #6b7280;
        }



        /* ============================================================
           DARK
        ============================================================ */

        .dark .area-summary > div,
        .dark .area-panel {
            background: rgb(17 24 39);
            border-color: rgb(55 65 81);
        }


        .dark .area-panel-header {
            border-color: rgb(55 65 81);
        }


        .dark .area-store-card + .area-store-card {
            border-color: rgb(55 65 81);
        }


        .dark .area-store-card:hover,
        .dark .area-store-hours > div {
            background: rgb(31 41 55);
        }



        /* ============================================================
           RESPONSIVE
        ============================================================ */

        @media (max-width: 1000px) {

            .area-summary {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }


            .area-detail-grid {
                grid-template-columns: 1fr;
            }


            .area-store-list {
                max-height: none;
            }

        }


        @media (max-width: 600px) {

            .area-summary {
                grid-template-columns: 1fr 1fr;
            }


            .area-map {
                height: 430px;
            }

        }

    </style>

@endpush

@push('scripts')
    <script>

        window.__areaStoreMapData =
            @json($mapStores);


        async function initializeAreaStoreMap()
        {
            const mapElement =
                document.getElementById(
                    'area-store-map'
                );


            if (
                ! mapElement
                ||
                mapElement.dataset.initialized
                ===
                '1'
            ) {
                return;
            }


            const stores =
                window.__areaStoreMapData
                || [];


            if (
                stores.length === 0
            ) {
                return;
            }


            try {

                /*
                 * Loader yang sudah digunakan
                 * oleh project.
                 */
                if (
                    typeof
                    window.ensureGoogleMapsApi
                    !==
                    'function'
                ) {
                    throw new Error(
                        'Google Maps loader belum tersedia.'
                    );
                }


                await window
                    .ensureGoogleMapsApi();


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


                mapElement.dataset.initialized =
                    '1';


                const firstStore =
                    stores[0];


                const map =
                    new Map(
                        mapElement,
                        {
                            center: {
                                lat:
                                    Number(
                                        firstStore.latitude
                                    ),

                                lng:
                                    Number(
                                        firstStore.longitude
                                    ),
                            },

                            zoom:
                                13,

                            mapId:
                                window
                                    .__routeOptimizationMapId
                                ||
                                'DEMO_MAP_ID',

                            streetViewControl:
                                false,

                            mapTypeControl:
                                false,

                            fullscreenControl:
                                true,
                        }
                    );


                const bounds =
                    new google.maps
                        .LatLngBounds();


                const infoWindow =
                    new google.maps
                        .InfoWindow();


                stores.forEach(
                    (
                        store,
                        index
                    ) => {

                        const lat =
                            Number(
                                store.latitude
                            );


                        const lng =
                            Number(
                                store.longitude
                            );


                        if (
                            ! Number.isFinite(
                                lat
                            )
                            ||
                            ! Number.isFinite(
                                lng
                            )
                        ) {
                            return;
                        }


                        const position = {
                            lat,
                            lng,
                        };


                        /*
                         * Nomor pin sama dengan
                         * urutan di panel anggota.
                         */
                        const pin =
                            new PinElement({
                                glyphText:
                                    String(
                                        index + 1
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
                                    store.name
                                    ||
                                    'Store',

                                content:
                                    pin,

                                gmpClickable:
                                    true,
                            });


                        marker.addListener(
                            'gmp-click',
                            () => {

                                /*
                                 * Pakai DOM, bukan HTML string,
                                 * supaya data Store tidak
                                 * dimasukkan sebagai raw HTML.
                                 */
                                const wrapper =
                                    document
                                        .createElement(
                                            'div'
                                        );


                                wrapper.className =
                                    'area-map-info';


                                const title =
                                    document
                                        .createElement(
                                            'div'
                                        );


                                title.className =
                                    'area-map-info-title';


                                title.textContent =
                                    store.name
                                    ||
                                    'Store';


                                wrapper.appendChild(
                                    title
                                );


                                const code =
                                    document
                                        .createElement(
                                            'div'
                                        );


                                code.className =
                                    'area-map-info-code';


                                code.textContent =
                                    store.code
                                    ||
                                    '-';


                                wrapper.appendChild(
                                    code
                                );


                                const address =
                                    document
                                        .createElement(
                                            'div'
                                        );


                                address.className =
                                    'area-map-info-address';


                                address.textContent =
                                    store.address
                                    ||
                                    '-';


                                wrapper.appendChild(
                                    address
                                );


                                const hours =
                                    document
                                        .createElement(
                                            'div'
                                        );


                                hours.className =
                                    'area-map-info-hours';


                                hours.textContent =
                                    `Jam ${store.opening_time || '-'} - ${store.closing_time || '-'}`;


                                wrapper.appendChild(
                                    hours
                                );


                                infoWindow.setContent(
                                    wrapper
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
                 * Auto focus semua anggota Area.
                 */
                if (
                    stores.length === 1
                ) {

                    map.setCenter(
                        bounds.getCenter()
                    );


                    map.setZoom(
                        15
                    );

                } else {

                    map.fitBounds(
                        bounds,
                        60
                    );
                }


            } catch (error) {

                console.error(
                    'Area Store Map:',
                    error
                );


                mapElement.innerHTML =
                    '<div style="padding:16px;font-size:12px;color:#b91c1c;">'
                    + 'Map gagal dimuat: '
                    + String(
                        error?.message
                        || error
                    )
                    + '</div>';
            }
        }


        document.addEventListener(
            'DOMContentLoaded',
            initializeAreaStoreMap
        );


        document.addEventListener(
            'livewire:navigated',
            initializeAreaStoreMap
        );


        /*
         * Untuk kondisi script dijalankan
         * setelah DOMContentLoaded.
         */
        if (
            document.readyState
            !==
            'loading'
        ) {
            initializeAreaStoreMap();
        }

    </script>
    
@endpush