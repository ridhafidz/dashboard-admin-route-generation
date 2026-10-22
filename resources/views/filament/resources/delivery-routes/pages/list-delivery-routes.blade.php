<x-filament-panels::page>
    <div
        x-data="{
            rescheduleOpen: false,
            manualRerouteOpen: false,
            selectedStopId: null,
            selectedStopName: '',
            newRouteDate: '{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}',
        }"
        @control-tower-takeout.window="
            if (confirm(`Takeout ${$event.detail.storeName || 'customer'} dari route ini? Route asal akan dioptimasi ulang.`)) {
                $wire.takeoutStop($event.detail.stopId);
            }
        "
        @control-tower-reschedule.window="
            selectedStopId = $event.detail.stopId;
            selectedStopName = $event.detail.storeName || '';
            newRouteDate = $event.detail.minDate || '{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}';
            rescheduleOpen = true;
        "
        @control-tower-manual-reroute.window="
            selectedStopId = $event.detail.stopId;
            selectedStopName = $event.detail.storeName || '';
            manualRerouteOpen = true;
            $wire.loadManualRerouteTargets(selectedStopId);
        "
        @manual-reroute-finished.window="manualRerouteOpen = false"
        class="ct-root"
    >
        <section class="ct-toolbar">
            <div>
                <label class="ct-label">Tanggal Route</label>
                <select wire:model.live="mapDate" class="ct-select">
                    @forelse ($routeDateOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @empty
                        <option value="{{ $mapDate }}">{{ \Carbon\Carbon::parse($mapDate)->format('d-m-Y') }}</option>
                    @endforelse
                </select>
            </div>

            <div class="ct-summary">
                <div>
                    <span>Route</span>
                    <strong>{{ $controlTowerData['route_count'] ?? 0 }}</strong>
                </div>
                <div>
                    <span>Total Stop</span>
                    <strong>{{ $controlTowerData['stop_count'] ?? 0 }}</strong>
                </div>
            </div>
        </section>

        @if (count($deferredDemands ?? []) > 0)
            <section class="ct-deferred-card">
                <div class="ct-deferred-head">
                    <div>
                        <h3>Demand Belum Teralokasi</h3>
                        <p>
                            Demand berikut belum dapat dimasukkan ke route hasil optimizer.
                        </p>
                    </div>

                    <span class="ct-deferred-count">
                        {{ count($deferredDemands) }} demand
                    </span>
                </div>

                <div class="ct-deferred-list">
                    @foreach ($deferredDemands as $item)
                        @php
                            $reason = $item['reason'] ?? 'UNKNOWN';

                            $reasonLabel = match ($reason) {
                                'CAPACITY_FULL' => 'Capacity Full',
                                'NO_COMPATIBLE_VEHICLE' => 'No Compatible Vehicle',
                                'UNASSIGNABLE_CAPACITY' => 'Unassignable Capacity',
                                default => $reason,
                            };

                            $reasonClass = match ($reason) {
                                'CAPACITY_FULL' => 'warning',
                                'NO_COMPATIBLE_VEHICLE' => 'danger',
                                'UNASSIGNABLE_CAPACITY' => 'danger',
                                default => 'gray',
                            };
                        @endphp

                        <div class="ct-deferred-item">
                            <div class="ct-deferred-main">
                                <strong>
                                    {{ $item['customer_name'] ?? '-' }}
                                </strong>

                                <span>
                                    Customer:
                                    {{ $item['customer_id'] ?? '-' }}
                                </span>
                            </div>

                            <div class="ct-deferred-meta">
                                <div>
                                    <span>Box Type</span>
                                    <strong>
                                        {{ $item['box_type'] ?? '-' }}
                                    </strong>
                                </div>

                                <div>
                                    <span>Volume</span>
                                    <strong>
                                        {{ number_format(
                                            ($item['demand_volume_cm3'] ?? 0) / 1000000,
                                            2,
                                            ',',
                                            '.'
                                        ) }} m³
                                    </strong>
                                </div>

                                <div>
                                    <span>SO</span>
                                    <strong>
                                        {{ implode(
                                            ', ',
                                            $item['so_numbers'] ?? []
                                        ) ?: '-' }}
                                    </strong>
                                </div>
                            </div>

                            <div
                                class="ct-deferred-reason
                                       ct-deferred-reason-{{ $reasonClass }}"
                            >
                                {{ $reasonLabel }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($invalidItems ?? []) > 0)
            <section class="ct-invalid-card">
                <div class="ct-invalid-head">
                    <div>
                        <h3>SO Belum Siap Routing</h3>
                        <p>
                            SO berikut disisihkan sementara karena master produk atau data kubikasi belum lengkap.
                            SO lain yang valid tetap diproses ke optimizer.
                        </p>
                    </div>

                    <span class="ct-invalid-count">
                        {{ $invalidSoCount ?? 0 }} SO
                    </span>
                </div>

                <div class="ct-invalid-list">
                    @foreach ($invalidItems as $item)
                        @php
                            $reason = $item['reason'] ?? 'UNKNOWN';

                            $reasonLabel = match ($reason) {
                                'MASTER_PRODUCT_NOT_FOUND' =>
                                    'Master Product Belum Ada',

                                'INVALID_PRODUCT_CUBICATION' =>
                                    'Kubikasi Produk Belum Valid',

                                'UNITS_PER_PACK_MISSING' =>
                                    'Units Per Pack Belum Diisi',

                                default =>
                                    $reason,
                            };
                        @endphp

                        <div class="ct-invalid-item">
                            <div class="ct-invalid-title">
                                <div>
                                    <strong>
                                        {{ $item['customer_name'] ?? '-' }}
                                    </strong>

                                    <span>
                                        Customer:
                                        {{ $item['customer_id'] ?? '-' }}
                                    </span>
                                </div>

                                <div class="ct-invalid-badge">
                                    {{ $reasonLabel }}
                                </div>
                            </div>

                            <div class="ct-invalid-meta">
                                <div>
                                    <span>SO Number</span>

                                    <strong>
                                        {{ $item['so_number'] ?? '-' }}
                                    </strong>
                                </div>

                                <div>
                                    <span>Produk</span>

                                    <strong>
                                        {{ $item['product_code'] ?? '-' }}
                                    </strong>

                                    <small>
                                        {{ $item['product_name'] ?? '-' }}
                                    </small>
                                </div>

                                <div>
                                    <span>Tanggal SO</span>

                                    <strong>
                                        @if (! empty($item['source_date']))
                                            {{ \Carbon\Carbon::parse(
                                                $item['source_date']
                                            )->format('d-m-Y') }}
                                        @else
                                            -
                                        @endif
                                    </strong>
                                </div>
                            </div>

                            <div class="ct-invalid-message">
                                <strong>Alasan:</strong>
                                {{ $item['message'] ?? '-' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (blank($googleMapsBrowserKey))
            <div class="ct-alert">
                GOOGLE_MAPS_BROWSER_KEY belum dikonfigurasi.
            </div>
        @endif

        <section class="ct-map-card">
            <div class="ct-map-head">
                <div>
                    <h2>Control Tower Delivery</h2>
                    <p>
                        Warna menunjukkan route yang berbeda. Nomor pada marker adalah sequence pengantaran.
                        Garis route menggunakan geometry OSRM.
                    </p>
                </div>
            </div>

            <div
                wire:key="delivery-control-map-{{ $mapDate }}-{{ $mapRevision }}"
                x-data="{ payload: @js($controlTowerData) }"
                x-init="setTimeout(() => window.initDeliveryControlTower($el, payload), 80)"
                class="ct-map-wrap"
            >
                <div class="ct-map-canvas" wire:ignore></div>
                <div class="ct-map-error" hidden></div>
            </div>
        </section>

        <section class="ct-legend-card">
            <div class="ct-legend-head">
                <h3>Route Aktif</h3>
                <span>{{ \Carbon\Carbon::parse($controlTowerData['route_date'] ?? $mapDate)->format('d M Y') }}</span>
            </div>

            <div class="ct-legend-grid">
                @forelse (($controlTowerData['routes'] ?? []) as $route)
                    <div class="ct-route-item">

                        <span
                            class="ct-route-dot"
                            style="background: {{ $route['color'] }}"
                        ></span>

                        <div class="ct-route-content">

                            <div class="ct-route-title">
                                <div>
                                    <strong>
                                        Route #{{ $route['id'] }}
                                    </strong>

                                    <span>
                                        {{ $route['driver']['name'] }}
                                        ·
                                        {{ $route['vehicle']['plate_number'] }}
                                    </span>
                                </div>

                                <b>
                                    {{ $route['stop_count'] }} stop
                                </b>
                            </div>

                            <div class="ct-route-summary">

                                <div>
                                    <span>Jarak</span>
                                    <strong>
                                        {{ number_format(
                                            $route['distance_km'] ?? 0,
                                            2,
                                            ',',
                                            '.'
                                        ) }} km
                                    </strong>
                                </div>

                                <div>
                                    <span>Travel</span>
                                    <strong>
                                        @php
                                            $travel =
                                                (int) (
                                                    $route['travel_duration_minutes']
                                                    ?? 0
                                                );

                                            $travelHour =
                                                intdiv(
                                                    $travel,
                                                    60
                                                );

                                            $travelMinute =
                                                $travel % 60;
                                        @endphp

                                        @if ($travelHour > 0)
                                            {{ $travelHour }}j
                                        @endif

                                        {{ $travelMinute }}m
                                    </strong>
                                </div>

                                <div>
                                    <span>Total Route</span>
                                    <strong>
                                        @php
                                            $duration =
                                                (int) (
                                                    $route[
                                                        'predicted_duration_minutes'
                                                    ]
                                                    ?? 0
                                                );

                                            $durationHour =
                                                intdiv(
                                                    $duration,
                                                    60
                                                );

                                            $durationMinute =
                                                $duration % 60;
                                        @endphp

                                        @if ($durationHour > 0)
                                            {{ $durationHour }}j
                                        @endif

                                        {{ $durationMinute }}m
                                    </strong>
                                </div>

                                <div>
                                    <span>Estimasi</span>
                                    <strong>
                                        {{ $route['start_time'] ?? '-' }}
                                        →
                                        {{ $route['finish_time'] ?? '-' }}
                                    </strong>
                                </div>

                            </div>

                            <div class="ct-route-meta">

                                <span>
                                    Status:
                                    <strong>
                                        {{ ucfirst(
                                            $route['status']
                                            ?? '-'
                                        ) }}
                                    </strong>
                                </span>

                                <span>
                                    Approval:
                                    <strong>
                                        {{ ucfirst(
                                            $route['approval_status']
                                            ?? '-'
                                        ) }}
                                    </strong>
                                </span>

                            </div>

                            @if (
                                ($route['status'] ?? null) === 'planned'
                                &&
                                ($route['approval_status'] ?? null)
                                === 'pending'
                            )

                                <div class="ct-route-actions">

                                    <button
                                        type="button"
                                        class="ct-btn ct-btn-success"
                                        wire:click="approveRoute({{ $route['id'] }})"
                                        wire:confirm="Approve Route #{{ $route['id'] }}? Setelah disetujui route akan tersedia untuk Driver."
                                    >
                                        Approve Route
                                    </button>

                                    <button
                                        type="button"
                                        class="ct-btn ct-btn-danger"
                                        wire:click="cancelRoute({{ $route['id'] }})"
                                        wire:confirm="Batalkan Route #{{ $route['id'] }}?"
                                    >
                                        Cancel Route
                                    </button>

                                </div>

                            @elseif (
                                ($route['approval_status'] ?? null)
                                    === 'approved'
                            )

                                <div class="ct-route-approved">
                                    ✓ Route Approved
                                </div>

                            @endif
                        </div>
                    </div>
                @empty
                    <div class="ct-empty">Belum ada route pada tanggal ini.</div>
                @endforelse
            </div>
        </section>

        {{-- RESCHEDULE MODAL --}}
        <div x-show="rescheduleOpen" x-cloak class="ct-modal-overlay" @click.self="rescheduleOpen = false">
            <div class="ct-modal">
                <div class="ct-modal-head">
                    <div>
                        <h3>Reschedule Stop</h3>
                        <span x-text="selectedStopName"></span>
                    </div>
                    <button type="button" @click="rescheduleOpen = false">&times;</button>
                </div>
                <div class="ct-modal-body">
                    <label>Tanggal route baru</label>
                    <input type="date" x-model="newRouteDate" class="ct-input">
                    <p>
                        Stop akan dikeluarkan dari route sekarang, route asal dioptimasi ulang,
                        dan reschedule dicatat pada antrean V3.
                    </p>
                </div>
                <div class="ct-modal-foot">
                    <button type="button" class="ct-btn ct-btn-light" @click="rescheduleOpen = false">Batal</button>
                    <button
                        type="button"
                        class="ct-btn ct-btn-warning"
                        @click="
                            const id = selectedStopId;
                            const date = newRouteDate;
                            rescheduleOpen = false;
                            $wire.rescheduleStop(id, date);
                        "
                    >
                        Reschedule
                    </button>
                </div>
            </div>
        </div>

        {{-- MANUAL REROUTING MODAL --}}
        <div
            x-show="manualRerouteOpen"
            x-cloak
            class="ct-modal-overlay"
            @click.self="manualRerouteOpen = false"
        >
            <div class="ct-modal ct-modal-wide">
                <div class="ct-modal-head">
                    <div>
                        <h3>Manual Rerouting</h3>
                        <span>
                            {{ $manualRerouteStoreName ?? 'Memuat target route...' }}
                            @if ($manualRerouteSourceRouteId)
                                · dari Route #{{ $manualRerouteSourceRouteId }}
                            @endif
                        </span>
                    </div>
                    <button
                        type="button"
                        @click="manualRerouteOpen = false"
                    >&times;</button>
                </div>

                <div class="ct-modal-body ct-reroute-body">
                    <div
                        wire:loading.flex
                        wire:target="loadManualRerouteTargets"
                        class="ct-loading"
                    >
                        <div class="ct-spinner"></div>
                        <div>
                            <strong>Memuat route tujuan...</strong>
                            <span>
                                Tahap ini hanya membaca route yang tersedia.
                                OSRM dan OR-Tools belum dijalankan.
                            </span>
                        </div>
                    </div>

                    <div
                        wire:loading.remove
                        wire:target="loadManualRerouteTargets"
                    >
                        @if ($manualRerouteError)
                            <div class="ct-error">{{ $manualRerouteError }}</div>
                        @elseif (count($manualRerouteTargets) > 0)
                            <p class="ct-manual-note">
                                Pilih route tujuan. Setelah dipilih, sistem hanya menghitung ulang
                                <strong>route asal</strong> dan <strong>route tujuan</strong>.
                                Sequence, ETA, service time, dan durasi kedua route akan diperbarui otomatis.
                            </p>

                            <div class="ct-target-list">
                                @foreach ($manualRerouteTargets as $target)
                                    <div class="ct-target-row">
                                        <div class="ct-target-info">
                                            <strong>Route #{{ $target['id'] }}</strong>
                                            <span>
                                                {{ $target['driver_name'] }}
                                                · {{ $target['vehicle_plate'] }}
                                                · {{ $target['stop_count'] }} stop
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            class="ct-btn ct-btn-primary"
                                            wire:click="executeManualReroute({{ $target['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="executeManualReroute({{ $target['id'] }})"
                                        >
                                            <span
                                                wire:loading.remove
                                                wire:target="executeManualReroute({{ $target['id'] }})"
                                            >
                                                Pindahkan & Optimasi
                                            </span>
                                            <span
                                                wire:loading
                                                wire:target="executeManualReroute({{ $target['id'] }})"
                                            >
                                                Mengoptimasi...
                                            </span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="ct-empty">
                                Belum ada route target Planned + Pending
                                pada cabang dan tanggal yang sama.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Filament action modal renderer.
         Wajib ada karena page ini memakai custom Blade view dan header action
         Generate Route membuka modal Filament. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>

@push('styles')
<style>
[x-cloak]{display:none!important}.ct-root{display:flex;flex-direction:column;gap:16px}.ct-toolbar,.ct-map-card,.ct-legend-card{background:var(--fi-color-gray-50,#fff);border:1px solid #e5e7eb;border-radius:16px}.dark .ct-toolbar,.dark .ct-map-card,.dark .ct-legend-card{background:#111827;border-color:#374151}.ct-toolbar{padding:16px 18px;display:flex;align-items:end;justify-content:space-between;gap:16px;flex-wrap:wrap}.ct-label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px}.ct-select,.ct-input{min-width:190px;border:1px solid #d1d5db;border-radius:9px;padding:8px 10px;background:#fff}.dark .ct-select,.dark .ct-input{background:#1f2937;border-color:#4b5563}.ct-summary{display:flex;gap:24px}.ct-summary div{display:flex;flex-direction:column}.ct-summary span{font-size:11px;color:#6b7280}.ct-summary strong{font-size:20px}.ct-alert,.ct-error{padding:12px 14px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:10px}.ct-map-head,.ct-legend-head{padding:16px 18px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}.dark .ct-map-head,.dark .ct-legend-head{border-color:#374151}.ct-map-head h2,.ct-legend-head h3{font-weight:700;margin:0}.ct-map-head p{font-size:12px;color:#6b7280;margin:4px 0 0}.ct-map-canvas{height:68vh;min-height:520px;border-radius:0 0 16px 16px}.ct-map-error{padding:12px;color:#991b1b}.ct-legend-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;padding:14px}.ct-route-item{display:flex;align-items:center;gap:10px;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;text-decoration:none;color:inherit}.dark .ct-route-item{border-color:#374151}.ct-route-content {min-width:0;flex:1}.ct-route-item strong,.ct-route-item span{display:block}.ct-route-item span{font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ct-route-item b{font-size:11px;color:#6b7280}.ct-route-dot{width:12px;height:12px;border-radius:999px;flex-shrink:0}.ct-empty{padding:22px;text-align:center;color:#6b7280}.ct-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px}.ct-modal{width:100%;max-width:440px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.28)}.dark .ct-modal{background:#111827;border:1px solid #374151}.ct-modal-wide{max-width:760px}.ct-modal-head,.ct-modal-foot{padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}.ct-modal-head{border-bottom:1px solid #e5e7eb}.ct-modal-foot{border-top:1px solid #e5e7eb;justify-content:flex-end}.dark .ct-modal-head,.dark .ct-modal-foot{border-color:#374151}.ct-modal-head h3{font-weight:700}.ct-modal-head span{font-size:11px;color:#6b7280}.ct-modal-head button{font-size:24px;color:#6b7280}.ct-modal-body{padding:18px}.ct-modal-body label{display:block;font-size:12px;font-weight:600;margin-bottom:6px}.ct-modal-body p{font-size:11px;line-height:1.6;color:#6b7280;margin-top:10px}.ct-btn{border:0;border-radius:8px;padding:8px 13px;font-size:12px;font-weight:700;cursor:pointer}.ct-btn-light{background:#f3f4f6}.ct-btn-warning{background:#f59e0b;color:#fff}.ct-btn-primary{background:#2563eb;color:#fff}.ct-loading{min-height:180px;align-items:center;justify-content:center;gap:12px}.ct-loading span{display:block;font-size:11px;color:#6b7280;margin-top:3px}.ct-spinner{width:28px;height:28px;border:3px solid #e5e7eb;border-top-color:#2563eb;border-radius:999px;animation:ct-spin .7s linear infinite}@keyframes ct-spin{to{transform:rotate(360deg)}}.ct-candidates{display:flex;flex-direction:column;gap:10px}.ct-candidate{border:1px solid #e5e7eb;border-radius:12px;padding:13px}.ct-candidate-best{border-color:#60a5fa;background:#eff6ff}.dark .ct-candidate-best{background:rgba(30,58,138,.25)}.ct-candidate-head{display:flex;justify-content:space-between;gap:10px}.ct-candidate-head strong,.ct-candidate-head span{display:block}.ct-candidate-head span{font-size:11px;color:#6b7280}.ct-best{display:inline-block!important;width:max-content;background:#2563eb!important;color:#fff!important;padding:2px 7px;border-radius:999px;font-size:9px!important;margin-bottom:4px}.ct-candidate-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-top:10px}.ct-candidate-metrics div{background:#f9fafb;border-radius:8px;padding:8px}.dark .ct-candidate-metrics div{background:#1f2937}.ct-candidate-metrics span,.ct-candidate-metrics strong{display:block}.ct-candidate-metrics span{font-size:9px;color:#6b7280}.ct-candidate-metrics strong{font-size:11px;margin-top:2px}.ct-candidate-foot{display:flex;justify-content:flex-end;margin-top:10px}.ct-manual-note{font-size:12px!important;line-height:1.6!important;color:#6b7280!important;margin:0 0 12px!important}.ct-target-list{display:flex;flex-direction:column;gap:9px}.ct-target-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e5e7eb;border-radius:10px;padding:11px 12px}.dark .ct-target-row{border-color:#374151}.ct-target-info{min-width:0}.ct-target-info strong,.ct-target-info span{display:block}.ct-target-info strong{font-size:13px}.ct-target-info span{font-size:11px;color:#6b7280;margin-top:2px}.ct-target-row .ct-btn{flex-shrink:0}@media(max-width:768px){.ct-map-canvas{height:60vh;min-height:420px}.ct-candidate-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}

.ct-route-content {
    flex: 1;
    min-width: 0;
}

.ct-route-title {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.ct-route-title strong,
.ct-route-title span {
    display: block;
}

.ct-route-title span {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
}

.ct-route-summary {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
}

.ct-route-summary div {
    background: #f9fafb;
    padding: 8px 10px;
    border-radius: 8px;
}

.dark .ct-route-summary div {
    background: #1f2937;
}

.ct-route-summary span,
.ct-route-summary strong {
    display: block;
}

.ct-route-summary span {
    font-size: 9px;
    color: #6b7280;
}

.ct-route-summary strong {
    font-size: 12px;
    margin-top: 2px;
}

.ct-route-meta {
    display: flex;
    gap: 18px;
    margin-top: 10px;
    font-size: 11px;
    color: #6b7280;
}

.ct-route-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid #e5e7eb;
}

.ct-btn-success {
    background: #16a34a;
    color: #fff;
}

.ct-btn-danger {
    background: #dc2626;
    color: #fff;
}

.ct-route-approved {
    margin-top: 12px;
    padding: 8px 10px;
    background: #ecfdf5;
    border-radius: 8px;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
}

@media (max-width: 768px) {
    .ct-route-summary {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

.ct-deferred-card{
    background:var(--fi-color-gray-50,#fff);
    border:1px solid #e5e7eb;
    border-radius:16px;
    overflow:hidden;
}

.dark .ct-deferred-card{
    background:#111827;
    border-color:#374151;
}

.ct-deferred-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    padding:16px 18px;
    border-bottom:1px solid #e5e7eb;
}

.dark .ct-deferred-head{
    border-color:#374151;
}

.ct-deferred-head h3{
    font-weight:700;
    margin:0;
}

.ct-deferred-head p{
    font-size:12px;
    color:#6b7280;
    margin:4px 0 0;
}

.ct-deferred-count{
    padding:5px 9px;
    border-radius:999px;
    background:#fef3c7;
    color:#92400e;
    font-size:11px;
    font-weight:700;
}

.ct-deferred-list{
    display:flex;
    flex-direction:column;
    gap:10px;
    padding:14px;
}

.ct-deferred-item{
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:13px;
}

.dark .ct-deferred-item{
    border-color:#374151;
}

.ct-deferred-main strong,
.ct-deferred-main span{
    display:block;
}

.ct-deferred-main span{
    margin-top:2px;
    font-size:11px;
    color:#6b7280;
}

.ct-deferred-meta{
    display:grid;
    grid-template-columns:
        repeat(3,minmax(0,1fr));
    gap:8px;
    margin-top:12px;
}

.ct-deferred-meta div{
    background:#f9fafb;
    border-radius:8px;
    padding:8px 10px;
}

.dark .ct-deferred-meta div{
    background:#1f2937;
}

.ct-deferred-meta span,
.ct-deferred-meta strong{
    display:block;
}

.ct-deferred-meta span{
    font-size:9px;
    color:#6b7280;
}

.ct-deferred-meta strong{
    font-size:11px;
    margin-top:2px;
}

.ct-deferred-reason{
    margin-top:10px;
    padding:7px 9px;
    border-radius:8px;
    font-size:11px;
    font-weight:700;
}

.ct-deferred-reason-warning{
    background:#fef3c7;
    color:#92400e;
}

.ct-deferred-reason-danger{
    background:#fee2e2;
    color:#991b1b;
}

.ct-deferred-reason-gray{
    background:#f3f4f6;
    color:#374151;
}

@media(max-width:768px){
    .ct-deferred-meta{
        grid-template-columns:1fr;
    }
}

.ct-invalid-card{
    background:var(--fi-color-gray-50,#fff);
    border:1px solid #fecaca;
    border-radius:16px;
    overflow:hidden;
}

.dark .ct-invalid-card{
    background:#111827;
    border-color:#7f1d1d;
}

.ct-invalid-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    padding:16px 18px;
    border-bottom:1px solid #fecaca;
    background:#fff7ed;
}

.dark .ct-invalid-head{
    background:rgba(127,29,29,.15);
    border-color:#7f1d1d;
}

.ct-invalid-head h3{
    margin:0;
    font-weight:700;
    color:#9a3412;
}

.dark .ct-invalid-head h3{
    color:#fdba74;
}

.ct-invalid-head p{
    margin:4px 0 0;
    font-size:12px;
    color:#6b7280;
}

.ct-invalid-count{
    flex-shrink:0;
    padding:5px 10px;
    border-radius:999px;
    background:#fee2e2;
    color:#991b1b;
    font-size:11px;
    font-weight:700;
}

.ct-invalid-list{
    display:flex;
    flex-direction:column;
    gap:10px;
    padding:14px;
}

.ct-invalid-item{
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:13px;
}

.dark .ct-invalid-item{
    border-color:#374151;
}

.ct-invalid-title{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
}

.ct-invalid-title strong,
.ct-invalid-title span{
    display:block;
}

.ct-invalid-title span{
    margin-top:2px;
    font-size:11px;
    color:#6b7280;
}

.ct-invalid-badge{
    flex-shrink:0;
    padding:4px 8px;
    border-radius:999px;
    background:#fee2e2;
    color:#991b1b;
    font-size:10px;
    font-weight:700;
}

.ct-invalid-meta{
    display:grid;
    grid-template-columns:
        repeat(3,minmax(0,1fr));
    gap:8px;
    margin-top:12px;
}

.ct-invalid-meta div{
    background:#f9fafb;
    border-radius:8px;
    padding:8px 10px;
}

.dark .ct-invalid-meta div{
    background:#1f2937;
}

.ct-invalid-meta span,
.ct-invalid-meta strong,
.ct-invalid-meta small{
    display:block;
}

.ct-invalid-meta span{
    font-size:9px;
    color:#6b7280;
}

.ct-invalid-meta strong{
    margin-top:2px;
    font-size:11px;
}

.ct-invalid-meta small{
    margin-top:2px;
    font-size:9px;
    color:#6b7280;
}

.ct-invalid-message{
    margin-top:10px;
    padding:9px 10px;
    border-radius:8px;
    background:#fef2f2;
    color:#991b1b;
    font-size:11px;
    line-height:1.5;
}

.dark .ct-invalid-message{
    background:rgba(127,29,29,.18);
    color:#fecaca;
}

@media(max-width:768px){
    .ct-invalid-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .ct-invalid-meta{
        grid-template-columns:1fr;
    }
}

</style>
@endpush

@push('scripts')
<script>
window.__controlTowerGoogleKey = @js($googleMapsBrowserKey);
window.__controlTowerMapId = @js($googleMapsMapId);

window.ensureControlTowerGoogleMapsApi = window.ensureControlTowerGoogleMapsApi || async function () {
    if (window.google?.maps?.importLibrary) return;
    if (typeof window.ensureGoogleMapsApi === 'function') {
        await window.ensureGoogleMapsApi();
        return;
    }
    if (window.__controlTowerGooglePromise) return window.__controlTowerGooglePromise;

    window.__controlTowerGooglePromise = new Promise((resolve, reject) => {
        const key = window.__controlTowerGoogleKey;
        if (!key) return reject(new Error('Google Maps Browser Key belum tersedia.'));
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&v=weekly&libraries=marker`;
        script.async = true;
        script.onload = resolve;
        script.onerror = () => reject(new Error('Google Maps gagal dimuat.'));
        document.head.appendChild(script);
    });

    return window.__controlTowerGooglePromise;
};

window.initDeliveryControlTower = async function (root, payload) {
    const mapElement = root.querySelector('.ct-map-canvas');
    const errorElement = root.querySelector('.ct-map-error');
    if (!mapElement) return;

    try {
        await window.ensureControlTowerGoogleMapsApi();
        const { Map } = await google.maps.importLibrary('maps');
        const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary('marker');
        const { LatLngBounds } = await google.maps.importLibrary('core');

        const branch = payload?.branch || {};
        const branchLat = Number(branch.latitude);
        const branchLng = Number(branch.longitude);
        if (!Number.isFinite(branchLat) || !Number.isFinite(branchLng)) {
            throw new Error('Koordinat branch tidak valid.');
        }

        const map = new Map(mapElement, {
            center: { lat: branchLat, lng: branchLng },
            zoom: 10,
            mapId: window.__controlTowerMapId || 'DEMO_MAP_ID',
            mapTypeControl: false,
            streetViewControl: false,
        });

        const infoWindow = new google.maps.InfoWindow();
        const bounds = new LatLngBounds();
        const branchPosition = { lat: branchLat, lng: branchLng };
        bounds.extend(branchPosition);

        const depotPin = new PinElement({ glyphText: 'D', glyphColor: '#fff', background: '#111827', borderColor: '#fff', scale: 1.15 });
        new AdvancedMarkerElement({ map, position: branchPosition, content: depotPin, title: `${branch.code || 'DEPOT'} - ${branch.name || 'Branch'}` });

        (payload.routes || []).forEach((route) => {
            const color = route.color || '#2563eb';
            const geometry = Array.isArray(route.geometry) ? route.geometry : [];

            if (geometry.length >= 2) {
                new google.maps.Polyline({
                    map,
                    path: geometry,
                    strokeColor: color,
                    strokeOpacity: .88,
                    strokeWeight: 5,
                    zIndex: 10,
                });
                geometry.forEach((point) => bounds.extend(point));
            }

            (route.stops || []).forEach((stop) => {
                const lat = Number(stop.store?.latitude);
                const lng = Number(stop.store?.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                const position = { lat, lng };
                bounds.extend(position);

                const pin = new PinElement({
                    glyphText: String(stop.sequence),
                    glyphColor: '#fff',
                    background: color,
                    borderColor: '#fff',
                    scale: 1.06,
                });

                const marker = new AdvancedMarkerElement({
                    map,
                    position,
                    content: pin,
                    title: `${stop.sequence}. ${stop.store?.name || 'Customer'} — Route #${route.id}`,
                    gmpClickable: true,
                });

                marker.addListener('gmp-click', () => {
                    const wrap = document.createElement('div');
                    wrap.style.minWidth = '275px';

                    const title = document.createElement('strong');
                    title.textContent = `${stop.sequence}. ${stop.store?.name || 'Customer'}`;
                    wrap.appendChild(title);

                    const info = document.createElement('div');
                    info.style.marginTop = '8px';
                    info.style.fontSize = '12px';
                    info.style.lineHeight = '1.65';

                    [
                        ['Route', `#${route.id}`],
                        ['Driver', route.driver?.name || '-'],
                        ['Vehicle', route.vehicle?.plate_number || '-'],
                        ['Customer ID', stop.customer_code || stop.store?.code || '-'],
                        ['Alamat', stop.store?.address || '-'],
                        ['Arrival', stop.predicted_arrival || '-'],
                        ['Service', `${stop.service_start || '-'} - ${stop.service_end || '-'}`],
                        ['Waiting', `${stop.waiting_minutes ?? 0} menit`],
                    ].forEach(([label, value]) => {
                        const row = document.createElement('div');
                        const b = document.createElement('strong');
                        b.textContent = `${label}: `;
                        row.appendChild(b);
                        row.appendChild(document.createTextNode(value));
                        info.appendChild(row);
                    });
                    wrap.appendChild(info);

                    const actions = document.createElement('div');
                    actions.style.display = 'grid';
                    actions.style.gridTemplateColumns = '1fr 1fr';
                    actions.style.gap = '6px';
                    actions.style.marginTop = '12px';
                    actions.style.paddingTop = '10px';
                    actions.style.borderTop = '1px solid #e5e7eb';

                    const makeButton = (label, background, eventName, full = false) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.textContent = label;
                        btn.style.border = '0';
                        btn.style.borderRadius = '7px';
                        btn.style.padding = '7px 8px';
                        btn.style.fontSize = '11px';
                        btn.style.fontWeight = '700';
                        btn.style.cursor = 'pointer';
                        btn.style.background = background;
                        btn.style.color = '#fff';
                        if (full) btn.style.gridColumn = '1 / -1';
                        btn.onclick = () => window.dispatchEvent(new CustomEvent(eventName, {
                            detail: {
                                stopId: Number(stop.id),
                                storeName: stop.store?.name || 'Customer',
                                routeId: Number(route.id),
                                minDate: route.route_date,
                            }
                        }));
                        actions.appendChild(btn);
                    };

                    if (stop.can_act) {
                        makeButton('Takeout', '#dc2626', 'control-tower-takeout');
                        makeButton('Reschedule', '#f59e0b', 'control-tower-reschedule');
                        makeButton('Manual Rerouting', '#2563eb', 'control-tower-manual-reroute', true);
                    }

                    wrap.appendChild(actions);
                    infoWindow.setContent(wrap);
                    infoWindow.open({anchor: marker,
                        map,
                    });
                });
            });
        });

        if ((payload.routes || []).length > 0) {
            map.fitBounds(bounds, 60);
        }
    } catch (error) {
        if (errorElement) {
            errorElement.hidden = false;
            errorElement.textContent = error?.message || 'Map gagal dimuat.';
        }
    }
};

</script>
@endpush
