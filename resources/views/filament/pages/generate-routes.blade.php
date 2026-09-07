<x-filament-panels::page>

    {{-- HEADER --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- LEFT: GENERATE ROUTE --}}
        <div>
            <div>
                <p class="text-base text-gray-700 dark:text-gray-300">
                    Driver yang saat ini berstatus
                    <strong>Ready</strong>:
                    {{ $readyDriverCount ?? 0 }} orang.
                    Vehicle Active tersedia: <strong>{{ $readyVehicleCount ?? 0 }}</strong>.
                </p>
            </div>
        </div>
    </div>


    {{-- MAP --}}
    <div
        id="route-map"
        wire:ignore
        class="mt-6 w-full overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700"
        style="height: calc(100vh - 230px);min-height: 650px;"
    ></div>

</x-filament-panels::page>

@push('styles')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        crossorigin=""
    />

    <style> 
        .custom-map-marker { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            width: 38px; 
            height: 38px; 
            border-radius: 50% 50% 50% 0; 
            transform: rotate(-45deg); 
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3); 
        } 
        
        .custom-map-marker span { 
            transform: rotate(45deg); 
            font-size: 20px; 
            line-height: 1; 
        } 
        
        .branch-marker { 
            background: #2563eb; 
            border: 3px solid #ffffff; 
        } 
        
        .store-marker { 
            background: #16a34a; 
            border: 3px solid #ffffff; 
        }

        #route-map {
            position: relative;
            z-index: 0 !important;
        }
        
    </style>
@endpush


@push('scripts')
    <script>
        window.__branchMarkers = @js($branchMarkers);
        window.__storeMarkers = @js($storeMarkers);

        let routeMap = null;
        let legendAdded = false;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function buildInfoContent(title, rows) {
            const rowsHtml = rows
                .map(([label, value]) => `<div><strong>${label}:</strong> ${escapeHtml(value ?? '-')}</div>`)
                .join('');
            return `<div style="min-width:220px;"><strong>${escapeHtml(title)}</strong><div style="margin-top:8px;">${rowsHtml}</div></div>`;
        }

        function makePin(background, glyphText) {
            return new google.maps.marker.PinElement({
                background,
                borderColor: '#ffffff',
                glyphColor: '#ffffff',
                glyphText: glyphText, // ganti dari 'glyph' ke 'glyphText'
                scale: 1.1,
            });
        }

        function initializeRouteMap() {
            const mapElement = document.getElementById('route-map');
            if (!mapElement || routeMap) return;
            if (typeof google === 'undefined' || !google.maps || !google.maps.marker) return;

            routeMap = new google.maps.Map(mapElement, {
                center: { lat: -7.2575, lng: 112.7521 },
                zoom: 10,
                mapId: @js(config('services.google_maps.map_id')),
            });

            const infoWindow = new google.maps.InfoWindow();
            const bounds = new google.maps.LatLngBounds();
            let hasPoints = false;

            (window.__branchMarkers || []).forEach((branch) => {
                const lat = Number(branch.latitude);
                const lng = Number(branch.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                const position = { lat, lng };
                const marker = new google.maps.marker.AdvancedMarkerElement({
                    map: routeMap,
                    position,
                    title: branch.name,
                    content: makePin('#2563eb', '🏢'),
                });

                marker.addListener('gmp-click', () => {
                    infoWindow.setContent(buildInfoContent(branch.name, [
                        ['CAB ID', branch.cab_id],
                        ['Inisial', branch.init_cab],
                        ['Region', branch.region_id],
                    ]));
                    infoWindow.open({ anchor: marker, map: routeMap });
                });

                bounds.extend(position);
                hasPoints = true;
            });

            (window.__storeMarkers || []).forEach((store) => {
                const lat = Number(store.latitude);
                const lng = Number(store.longitude);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                const position = { lat, lng };
                const marker = new google.maps.marker.AdvancedMarkerElement({
                    map: routeMap,
                    position,
                    title: store.name,
                    content: makePin('#16a34a', '🏪'),
                });

                marker.addListener('gmp-click', () => {
                    infoWindow.setContent(buildInfoContent(store.name, [
                        ['Code', store.code],
                        ['Area', store.area_name],
                    ]));
                    infoWindow.open({ anchor: marker, map: routeMap });
                });

                bounds.extend(position);
                hasPoints = true;
            });

            if (hasPoints) {
                routeMap.fitBounds(bounds, 40);
            }

            addLegend();
        }

        function addLegend() {
            if (legendAdded || !routeMap) return;

            const div = document.createElement('div');
            div.style.background = 'white';
            div.style.padding = '10px 12px';
            div.style.margin = '10px';
            div.style.borderRadius = '8px';
            div.style.boxShadow = '0 1px 5px rgba(0,0,0,0.25)';
            div.style.fontSize = '13px';
            div.innerHTML = `
                <div style="font-weight:600;margin-bottom:6px;">Legend</div>
                <div style="margin-bottom:4px;"><span style="color:#2563eb;">●</span> Branch</div>
                <div><span style="color:#16a34a;">●</span> Store</div>
            `;

            routeMap.controls[google.maps.ControlPosition.RIGHT_BOTTOM].push(div);
            legendAdded = true;
        }

        window.initializeRouteMap = initializeRouteMap;

        document.addEventListener('livewire:navigated', () => {
            routeMap = null;
            legendAdded = false;
            initializeRouteMap();
        });
    </script>

    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initializeRouteMap&libraries=marker&loading=async"
        async
        defer
    ></script>
@endpush