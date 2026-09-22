<div
    wire:ignore
    x-data
    x-init="
        $nextTick(
            () => window.initMadLocationPicker(
                $el,
                $wire
            )
        )
    "

    data-latitude-input-id="{{ $latitudeInputId }}"
    data-longitude-input-id="{{ $longitudeInputId }}"

    @if (!empty($addressInputId))
        data-address-input-id="{{ $addressInputId }}"
    @endif

    style="width:100%;"
>
    <div
        style="
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        "
    >
        Cari Lokasi
    </div>


    <div
        data-location-autocomplete
        style="
            width: 100%;
            margin-bottom: 12px;
        "
    ></div>


    <div
        data-location-map
        style="
            width: 100%;
            height: 360px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #d1d5db;
        "
    ></div>


    <div
        style="
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
        "
    >
        Cari lokasi melalui Google,
        klik titik pada peta,
        atau geser marker untuk menyesuaikan posisi.
    </div>


    <div
        data-location-error
        hidden
        style="
            margin-top: 8px;
            color: #dc2626;
            font-size: 12px;
        "
    ></div>
</div>