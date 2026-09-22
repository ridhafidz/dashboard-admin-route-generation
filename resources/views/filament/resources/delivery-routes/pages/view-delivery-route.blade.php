<x-filament-panels::page>

    @php
        $routeStatusValue =
            $route->status instanceof \BackedEnum
                ? $route->status->value
                : (string) $route->status;
    @endphp


    {{-- ======================================================
         APPROVAL BANNER
    ======================================================= --}}

    @if ($isApproved)

        <div class="dr-approval-banner dr-approval-approved">

            <div class="dr-approval-banner-left">

                <svg class="dr-approval-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                </svg>

                <div>
                    <div class="dr-approval-title">
                        Route Approved
                    </div>
                    <div class="dr-approval-sub">
                        @if ($approvedByName)
                            Disetujui oleh {{ $approvedByName }}
                            @if ($approvedAt)
                                &middot; {{ $approvedAt }}
                            @endif
                        @else
                            {{ $approvedAt ?? '-' }}
                        @endif
                        &middot; Driver dapat melihat dan memulai route ini.
                    </div>
                </div>

            </div>

            @if ($canRevoke)
                <div class="dr-approval-banner-right">
                    <span class="dr-approval-hint">
                        Revoke via tombol di atas jika perlu diubah.
                    </span>
                </div>
            @endif

        </div>

    @else

        <div class="dr-approval-banner dr-approval-pending">

            <div class="dr-approval-banner-left">

                <svg class="dr-approval-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>

                <div>
                    <div class="dr-approval-title">
                        Pending Approval
                    </div>
                    <div class="dr-approval-sub">
                        Route ini belum disetujui Admin.
                        Driver tidak dapat melihat atau memulai route ini.
                    </div>
                </div>

            </div>

            @if ($canApprove)
                <div class="dr-approval-banner-right">
                    <span class="dr-approval-hint">
                        Review stop di bawah, lalu klik
                        <strong>Approve Route</strong> di pojok kanan atas.
                    </span>
                </div>
            @endif

        </div>

    @endif


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

                                @foreach ($stop['packages'] as $package)
                                    <div class="dr-package-row">


                                        <div class="dr-package-main">

                                            <strong>

                                                @if ($package['product_code'])

                                                    {{ $package['product_code'] }} -

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


                            {{-- APPROVAL ACTIONS (pending + planned only) --}}

                            @if ($stop['can_act'])

                                <div class="dr-stop-actions">


                                    {{-- RESCHEDULE --}}

                                    <div
                                        x-data="{
                                            open: false,
                                            newDate: '{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}',
                                        }"
                                    >

                                        <button
                                            type="button"
                                            class="dr-action-btn dr-action-reschedule"
                                            @click="open = true"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                            </svg>
                                            Reschedule
                                        </button>


                                        {{-- RESCHEDULE MODAL --}}

                                        <div
                                            x-show="open"
                                            x-transition
                                            class="dr-modal-overlay"
                                            @click.self="open = false"
                                        >

                                            <div class="dr-modal">

                                                <div class="dr-modal-header">
                                                    <h3>Reschedule Stop</h3>
                                                    <button type="button" @click="open = false" class="dr-modal-close">&times;</button>
                                                </div>


                                                <div class="dr-modal-body">

                                                    <p class="dr-modal-desc">
                                                        <strong>
                                                            {{ $stop['store']['name'] ?? '-' }}
                                                        </strong>
                                                        &middot;
                                                        Box: {{ strtoupper($stop['box_type'] ?? '-') }}
                                                    </p>

                                                    <p class="dr-modal-desc dr-modal-warn">
                                                        Packages akan dikembalikan ke status Pending
                                                        dan dijadwalkan ulang ke tanggal yang dipilih.
                                                    </p>

                                                    <label class="dr-modal-label">
                                                        Tanggal pengiriman baru
                                                    </label>

                                                    <input
                                                        type="date"
                                                        class="dr-modal-input"
                                                        x-model="newDate"
                                                        min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}"
                                                    />

                                                </div>


                                                <div class="dr-modal-footer">

                                                    <button
                                                        type="button"
                                                        class="dr-modal-cancel"
                                                        @click="open = false"
                                                    >
                                                        Batal
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="dr-modal-confirm"
                                                        @click="
                                                            open = false;
                                                            $wire.rescheduleStop({{ $stop['id'] }}, newDate)
                                                        "
                                                    >
                                                        Reschedule
                                                    </button>

                                                </div>

                                            </div>

                                        </div>

                                    </div>
                                    {{-- SMART REROUTE --}}

                                    <button
                                        type="button"
                                        class="dr-action-btn dr-action-move"

                                        @click="
                                            window.dispatchEvent(
                                                new CustomEvent(
                                                    'smart-reroute-open',
                                                    {
                                                        detail: {
                                                            stopId: {{ $stop['id'] }}
                                                        }
                                                    }
                                                )
                                            )
                                        "
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"
                                            />
                                        </svg>

                                        Smart Reroute
                                    </button>

                                </div>{{-- end dr-stop-actions --}}

                            @endif

                        </div>{{-- end stop-card --}}

                    </div>{{-- end timeline-row --}}

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
     SMART REROUTE MODAL
======================================================= --}}

<div
    x-data="{
        open: false,
    }"

    @smart-reroute-open.window="
        open = true;

        $wire.loadSmartRerouteRecommendations(
            $event.detail.stopId
        );
    "

    @smart-reroute-close.window="
        open = false;
    "

    x-show="open"
    x-cloak
    x-transition

    class="dr-modal-overlay"

    @click.self="open = false"
>

    <div class="dr-modal dr-smart-modal">


        {{-- HEADER --}}

        <div class="dr-modal-header">

            <div>

                <h3>
                    Smart Reroute
                </h3>

                <div class="dr-smart-subtitle">

                    {{
                        $smartRerouteStoreName
                        ?? 'Menganalisis route...'
                    }}

                </div>

            </div>


            <button
                type="button"
                @click="open = false"
                class="dr-modal-close"
            >
                &times;
            </button>

        </div>



        {{-- BODY --}}

        <div class="dr-modal-body dr-smart-body">

            {{-- LOADING --}}

            <div
                wire:loading.flex
                wire:target="loadSmartRerouteRecommendations"
                class="dr-smart-loading"
            >

                <div class="dr-smart-spinner"></div>

                <div>

                    <strong>
                        Menganalisis route...
                    </strong>

                    <span>
                        Menghitung Google Route Matrix dan
                        OR-Tools untuk mencari route yang
                        paling sesuai.
                    </span>

                </div>

            </div>

            {{-- RESULT --}}

            <div
                wire:loading.remove
                wire:target="loadSmartRerouteRecommendations"
            >

                @if ($smartRerouteError)

                    <div class="dr-smart-error">

                        <strong>
                            Smart Reroute gagal
                        </strong>

                        <div>
                            {{ $smartRerouteError }}
                        </div>

                    </div>


                @elseif (count($smartRerouteCandidates) > 0)
                    <div class="dr-smart-intro">

                        Kandidat diurutkan berdasarkan dampak
                        gabungan terhadap route asal dan route
                        target.

                    </div>

                    <div class="dr-smart-candidates">

                        @foreach ($smartRerouteCandidates as $candidate)
                            @php

                                $netKm =
                                    (float)
                                    $candidate[
                                        'net_distance_delta_km'
                                    ];

                                $netMinutes =
                                    (int)
                                    $candidate[
                                        'net_duration_delta_minutes'
                                    ];

                                $isSaving =
                                    $netKm < 0
                                    ||
                                    $netMinutes < 0;

                            @endphp

                            <div
                                class="
                                    dr-smart-candidate

                                    {{
                                        $candidate['rank'] === 1
                                            ? 'dr-smart-best'
                                            : ''
                                    }}
                                "
                            >

                                <div class="dr-smart-candidate-head">

                                    <div class="dr-smart-route-title">

                                        @if ($candidate['rank'] === 1)

                                            <span class="dr-smart-best-badge">
                                                Recommended
                                            </span>

                                        @endif

                                        <strong>

                                            Route
                                            #{{ $candidate['target_route_id'] }}

                                        </strong>

                                        <span>

                                            {{
                                                $candidate[
                                                    'driver_name'
                                                ]
                                            }}

                                            &middot;

                                            {{
                                                $candidate[
                                                    'vehicle_plate'
                                                ]
                                            }}

                                        </span>

                                    </div>


                                    <div class="dr-smart-rank">

                                        #{{ $candidate['rank'] }}

                                    </div>

                                </div>

                                <div class="dr-smart-grid">

                                    {{-- POSITION --}}

                                    <div>

                                        <span>
                                            Posisi
                                        </span>

                                        <strong>

                                            Sequence

                                            {{
                                                $candidate[
                                                    'suggested_sequence'
                                                ]
                                                ?? '-'
                                            }}

                                        </strong>

                                        <small>

                                            ETA

                                            {{
                                                $candidate[
                                                    'suggested_arrival_time'
                                                ]
                                                ?? '-'
                                            }}

                                        </small>

                                    </div>

                                    {{-- TARGET IMPACT --}}

                                    <div>

                                        <span>
                                            Tambahan Target
                                        </span>

                                        <strong>

                                            +{{
                                                number_format(
                                                    max(
                                                        0,
                                                        $candidate[
                                                            'target_extra_distance_km'
                                                        ]
                                                    ),
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                            km

                                        </strong>

                                        <small>

                                            +{{
                                                max(
                                                    0,
                                                    $candidate[
                                                        'target_extra_duration_minutes'
                                                    ]
                                                )
                                            }}
                                            menit

                                        </small>

                                    </div>



                                    {{-- CAPACITY --}}

                                    <div>

                                        <span>
                                            Capacity After
                                        </span>

                                        <strong>

                                            {{
                                                number_format(
                                                    $candidate[
                                                        'capacity_utilization_after_percent'
                                                    ],
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            }}%

                                        </strong>

                                        <small>
                                            {{ strtoupper($candidate['box_type']) }}
                                        </small>

                                    </div>


                                </div>



                                {{-- NET EFFECT --}}

                                <div
                                    class="
                                        dr-smart-net

                                        {{
                                            $isSaving
                                                ? 'dr-smart-net-saving'
                                                : 'dr-smart-net-extra'
                                        }}
                                    "
                                >

                                    <div>

                                        <span>
                                            Dampak Gabungan
                                        </span>


                                        <strong>

                                            @if ($netKm < 0)

                                                Hemat

                                                {{
                                                    number_format(
                                                        abs(
                                                            $netKm
                                                        ),
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                km

                                            @elseif ($netKm > 0)

                                                +

                                                {{
                                                    number_format(
                                                        $netKm,
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                km

                                            @else

                                                Jarak sama

                                            @endif

                                        </strong>

                                    </div>


                                    <div>

                                        <span>
                                            Waktu
                                        </span>

                                        <strong>

                                            @if ($netMinutes < 0)

                                                Hemat

                                                {{
                                                    abs(
                                                        $netMinutes
                                                    )
                                                }}
                                                menit

                                            @elseif ($netMinutes > 0)

                                                +

                                                {{
                                                    $netMinutes
                                                }}
                                                menit

                                            @else

                                                Sama

                                            @endif

                                        </strong>

                                    </div>

                                </div>



                                {{-- BEFORE / AFTER --}}

                                <div class="dr-smart-comparison">

                                    <span>

                                        Before:

                                        {{
                                            number_format(
                                                $candidate[
                                                    'combined_distance_before_km'
                                                ],
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        km

                                        ·

                                        {{
                                            $candidate[
                                                'combined_duration_before_minutes'
                                            ]
                                        }}
                                        menit

                                    </span>


                                    <span>
                                        →
                                    </span>


                                    <span>

                                        After:

                                        {{
                                            number_format(
                                                $candidate[
                                                    'combined_distance_after_km'
                                                ],
                                                2,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        km

                                        ·

                                        {{
                                            $candidate[
                                                'combined_duration_after_minutes'
                                            ]
                                        }}
                                        menit

                                    </span>

                                </div>



                                {{-- MOVE --}}

                                <div class="dr-smart-candidate-footer">

                                    <button
                                        type="button"
                                        class="dr-smart-move-btn"

                                        wire:click="
                                            executeSmartReroute(
                                                {{ $candidate['target_route_id'] }}
                                            )
                                        "

                                        wire:loading.attr="disabled"
                                        wire:target="
                                            executeSmartReroute(
                                                {{ $candidate['target_route_id'] }}
                                            )
                                        "
                                    >

                                        <span
                                            wire:loading.remove
                                            wire:target="
                                                executeSmartReroute(
                                                    {{ $candidate['target_route_id'] }}
                                                )
                                            "
                                        >
                                            Move Here
                                        </span>


                                        <span
                                            wire:loading
                                            wire:target="
                                                executeSmartReroute(
                                                    {{ $candidate['target_route_id'] }}
                                                )
                                            "
                                        >
                                            Rerouting...
                                        </span>

                                    </button>

                                </div>

                            </div>

                        @endforeach

                    </div>


                @else

                    <div class="dr-smart-empty">

                        <div class="dr-smart-empty-icon">
                            ↔
                        </div>

                        <strong>
                            Belum ada route target yang compatible
                        </strong>

                        <span>

                            Smart Reroute sudah memeriksa route lain
                            pada cabang dan tanggal yang sama.

                            Kandidat hanya akan muncul jika route lain
                            masih Planned + Pending, box type sesuai,
                            dan kapasitas vehicle masih mencukupi.

                        </span>

                    </div>


                    @if (count($smartRerouteRejected) > 0)
                        <div class="dr-smart-rejected">

                            <strong>
                                Route yang tidak eligible
                            </strong>

                            @foreach ($smartRerouteRejected as $rejected)
                                <div>

                                    Route
                                    #{{ $rejected['target_route_id'] ?? '-' }}

                                    &middot;

                                    {{
                                        $rejected[
                                            'vehicle_plate'
                                        ]
                                        ?? '-'
                                    }}

                                    <span>

                                        {{
                                            $rejected[
                                                'reason'
                                            ]
                                        }}

                                    </span>

                                </div>

                            @endforeach

                        </div>

                    @endif

                @endif

            </div>

        </div>



        {{-- FOOTER --}}

        <div class="dr-modal-footer">

            <button
                type="button"
                class="dr-modal-cancel"
                @click="open = false"
            >
                Tutup
            </button>

        </div>

    </div>

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

/* ============================================================
   APPROVAL BANNER
============================================================ */

.dr-approval-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 18px;
    border-radius: 12px;
    border: 1.5px solid;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.dr-approval-banner-left {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.dr-approval-icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    margin-top: 2px;
}

.dr-approval-title {
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 2px;
}

.dr-approval-sub {
    font-size: 12px;
    opacity: 0.85;
}

.dr-approval-hint {
    font-size: 12px;
    opacity: 0.8;
}

.dr-approval-approved {
    background: rgb(240 253 244);
    border-color: rgb(134 239 172);
    color: rgb(20 83 45);
}

.dark .dr-approval-approved {
    background: rgb(5 46 22);
    border-color: rgb(22 101 52);
    color: rgb(187 247 208);
}

.dr-approval-pending {
    background: rgb(255 251 235);
    border-color: rgb(252 211 77);
    color: rgb(120 53 15);
}

.dark .dr-approval-pending {
    background: rgb(69 26 3);
    border-color: rgb(146 64 14);
    color: rgb(253 230 138);
}


/* ============================================================
   STOP ACTION BUTTONS
============================================================ */

.dr-stop-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-top: 1px solid rgb(229 231 235);
    flex-wrap: wrap;
}

.dark .dr-stop-actions {
    border-top-color: rgb(55 65 81);
}

.dr-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    line-height: 1.4;
}

.dr-action-btn svg {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
}

.dr-action-reschedule {
    background: rgb(254 243 199);
    border-color: rgb(252 211 77);
    color: rgb(120 53 15);
}

.dr-action-reschedule:hover {
    background: rgb(252 211 77);
}

.dark .dr-action-reschedule {
    background: rgb(69 26 3);
    border-color: rgb(146 64 14);
    color: rgb(253 230 138);
}

.dr-action-move {
    background: rgb(239 246 255);
    border-color: rgb(147 197 253);
    color: rgb(29 78 216);
}

.dr-action-move:hover {
    background: rgb(219 234 254);
}

.dark .dr-action-move {
    background: rgb(30 58 138);
    border-color: rgb(37 99 235);
    color: rgb(191 219 254);
}

[x-cloak] {
    display: none !important;
}


/* ============================================================
   SMART REROUTE
============================================================ */

.dr-smart-modal {
    max-width: 720px;
}

.dr-smart-body {
    max-height: 72vh;
    overflow-y: auto;
}

.dr-smart-subtitle {
    margin-top: 3px;
    font-size: 12px;
    color: rgb(107 114 128);
}

.dr-smart-loading {
    min-height: 180px;
    align-items: center;
    justify-content: center;
    gap: 14px;
    text-align: left;
}

.dr-smart-loading strong {
    display: block;
    font-size: 14px;
}

.dr-smart-loading span {
    display: block;
    margin-top: 4px;
    max-width: 380px;
    font-size: 12px;
    color: rgb(107 114 128);
}

.dr-smart-spinner {
    width: 30px;
    height: 30px;
    border-radius: 999px;
    border: 3px solid rgb(229 231 235);
    border-top-color: rgb(37 99 235);
    animation: dr-smart-spin .75s linear infinite;
    flex-shrink: 0;
}

@keyframes dr-smart-spin {
    to {
        transform: rotate(360deg);
    }
}

.dr-smart-intro {
    margin-bottom: 12px;
    font-size: 12px;
    line-height: 1.5;
    color: rgb(107 114 128);
}

.dr-smart-candidates {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.dr-smart-candidate {
    border: 1.5px solid rgb(229 231 235);
    border-radius: 12px;
    padding: 14px;
    background: white;
}

.dr-smart-best {
    border-color: rgb(96 165 250);
    background: rgb(239 246 255);
}

.dr-smart-candidate-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.dr-smart-route-title strong {
    display: block;
    margin-top: 4px;
    font-size: 15px;
}

.dr-smart-route-title > span:not(.dr-smart-best-badge) {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    color: rgb(107 114 128);
}

.dr-smart-best-badge {
    display: inline-flex;
    padding: 3px 8px;
    border-radius: 999px;
    background: rgb(37 99 235);
    color: white;
    font-size: 10px;
    font-weight: 700;
}

.dr-smart-rank {
    width: 30px;
    height: 30px;
    border-radius: 999px;
    background: rgb(243 244 246);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
}

.dr-smart-grid {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.dr-smart-grid > div {
    background: rgb(249 250 251);
    border-radius: 9px;
    padding: 9px 10px;
}

.dr-smart-grid span,
.dr-smart-net span {
    display: block;
    font-size: 10px;
    color: rgb(107 114 128);
}

.dr-smart-grid strong,
.dr-smart-net strong {
    display: block;
    margin-top: 2px;
    font-size: 12px;
}

.dr-smart-grid small {
    display: block;
    margin-top: 2px;
    font-size: 10px;
    color: rgb(107 114 128);
}

.dr-smart-net {
    margin-top: 10px;
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 8px;
    padding: 10px;
    border-radius: 9px;
}

.dr-smart-net-saving {
    background: rgb(240 253 244);
    color: rgb(22 101 52);
}

.dr-smart-net-extra {
    background: rgb(255 247 237);
    color: rgb(154 52 18);
}

.dr-smart-comparison {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgb(209 213 219);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    flex-wrap: wrap;
    font-size: 11px;
    color: rgb(107 114 128);
}

.dr-smart-candidate-footer {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.dr-smart-move-btn {
    border: 0;
    border-radius: 8px;
    background: rgb(37 99 235);
    color: white;
    padding: 8px 15px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.dr-smart-move-btn:hover:not(:disabled) {
    background: rgb(29 78 216);
}

.dr-smart-move-btn:disabled {
    opacity: .55;
    cursor: wait;
}

.dr-smart-empty {
    min-height: 190px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 24px;
}

.dr-smart-empty-icon {
    width: 42px;
    height: 42px;
    margin-bottom: 10px;
    border-radius: 999px;
    background: rgb(239 246 255);
    color: rgb(37 99 235);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.dr-smart-empty strong {
    font-size: 14px;
}

.dr-smart-empty span {
    max-width: 500px;
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.6;
    color: rgb(107 114 128);
}

.dr-smart-error {
    padding: 12px;
    border-radius: 9px;
    border: 1px solid rgb(252 165 165);
    background: rgb(254 242 242);
    color: rgb(153 27 27);
    font-size: 12px;
}

.dr-smart-error strong {
    display: block;
    margin-bottom: 4px;
}

.dr-smart-rejected {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed rgb(209 213 219);
    font-size: 11px;
}

.dr-smart-rejected > strong {
    display: block;
    margin-bottom: 7px;
}

.dr-smart-rejected > div {
    margin-top: 5px;
}

.dr-smart-rejected span {
    display: block;
    color: rgb(107 114 128);
}


/* DARK */

.dark .dr-smart-candidate {
    background: rgb(17 24 39);
    border-color: rgb(55 65 81);
}

.dark .dr-smart-best {
    background: rgb(30 58 138 / 0.25);
    border-color: rgb(59 130 246);
}

.dark .dr-smart-grid > div {
    background: rgb(31 41 55);
}

.dark .dr-smart-rank {
    background: rgb(55 65 81);
}

.dark .dr-smart-net-saving {
    background: rgb(5 46 22);
    color: rgb(187 247 208);
}

.dark .dr-smart-net-extra {
    background: rgb(67 20 7);
    color: rgb(254 215 170);
}

.dr-action-no-target {
    font-size: 11px;
    color: rgb(156 163 175);
    font-style: italic;
}

.dr-map-reroute-btn {
    width: 100%;
    border: 0;
    border-radius: 7px;
    padding: 7px 10px;
    background: rgb(37 99 235);
    color: white;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.dr-map-reroute-btn:hover {
    background: rgb(29 78 216);
}

/* ============================================================
   MODAL OVERLAY + MODAL
============================================================ */

.dr-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.dr-modal {
    background: white;
    border-radius: 14px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
}

.dark .dr-modal {
    background: rgb(17 24 39);
    border: 1px solid rgb(55 65 81);
}

.dr-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid rgb(229 231 235);
}

.dark .dr-modal-header {
    border-bottom-color: rgb(55 65 81);
}

.dr-modal-header h3 {
    font-size: 15px;
    font-weight: 700;
    margin: 0;
}

.dr-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: rgb(107 114 128);
    line-height: 1;
    padding: 0 4px;
}

.dr-modal-body {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dr-modal-desc {
    font-size: 13px;
    color: rgb(55 65 81);
    margin: 0;
}

.dark .dr-modal-desc {
    color: rgb(209 213 219);
}

.dr-modal-warn {
    color: rgb(120 53 15);
    background: rgb(255 251 235);
    border: 1px solid rgb(252 211 77);
    padding: 8px 10px;
    border-radius: 8px;
}

.dark .dr-modal-warn {
    color: rgb(253 230 138);
    background: rgb(69 26 3);
    border-color: rgb(146 64 14);
}

.dr-modal-label {
    font-size: 12px;
    font-weight: 600;
    color: rgb(75 85 99);
    display: block;
    margin-bottom: -4px;
}

.dark .dr-modal-label {
    color: rgb(209 213 219);
}

.dr-modal-input {
    width: 100%;
    border: 1.5px solid rgb(209 213 219);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 13px;
    color: rgb(17 24 39);
    background: white;
    outline: none;
    transition: border-color 0.15s;
}

.dr-modal-input:focus {
    border-color: rgb(245 158 11);
}

.dark .dr-modal-input {
    background: rgb(31 41 55);
    border-color: rgb(75 85 99);
    color: white;
}

.dr-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid rgb(229 231 235);
    background: rgb(249 250 251);
}

.dark .dr-modal-footer {
    border-top-color: rgb(55 65 81);
    background: rgb(31 41 55);
}

.dr-modal-cancel {
    padding: 7px 16px;
    border-radius: 8px;
    border: 1.5px solid rgb(209 213 219);
    background: white;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: rgb(55 65 81);
    transition: background 0.15s;
}

.dr-modal-cancel:hover {
    background: rgb(243 244 246);
}

.dark .dr-modal-cancel {
    background: rgb(55 65 81);
    border-color: rgb(75 85 99);
    color: rgb(209 213 219);
}

.dr-modal-confirm {
    padding: 7px 16px;
    border-radius: 8px;
    border: none;
    background: rgb(245 158 11);
    color: white;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s;
}

.dr-modal-confirm:hover:not(:disabled) {
    background: rgb(217 119 6);
}

.dr-modal-confirm:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}


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
    rows,
    options = {}
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

    if (
        options.canAct
        &&
        options.stopId
    ) {

        const actions =
            document.createElement(
                'div'
            );

        actions.style.marginTop =
            '12px';

        actions.style.paddingTop =
            '10px';

        actions.style.borderTop =
            '1px solid #e5e7eb';

        const rerouteButton =
            document.createElement(
                'button'
            );

        rerouteButton.type =
            'button';

        rerouteButton.className =
            'dr-map-reroute-btn';

        rerouteButton.textContent =
            'Smart Reroute';

        rerouteButton.addEventListener(
            'click',
            () => {

                window.dispatchEvent(
                    new CustomEvent(
                        'smart-reroute-open',
                        {
                            detail: {
                                stopId:
                                    Number(
                                        options.stopId
                                    ),
                            },
                        }
                    )
                );
            }
        );

        actions.appendChild(
            rerouteButton
        );


        wrapper.appendChild(
            actions
        );
    }

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
                                        stop.store?.address || '-'
                                    ],

                                    [
                                        'Arrival',
                                        stop.predicted_arrival || '-'
                                    ],

                                    [
                                        'Service',
                                        `${stop.service_start || '-'} - ${stop.service_end || '-'}`
                                    ],

                                    [
                                        'Package',
                                        packageSummary || '-'
                                    ],
                                ],
                                {
                                    canAct:
                                        Boolean(
                                            stop.can_act
                                        ),
                                    stopId:
                                        stop.id,
                                }
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