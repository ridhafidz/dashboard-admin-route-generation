(function () {
    function setInputValue(
        inputId,
        value,
        triggerBlur = false
    ) {
        if (!inputId) {
            return;
        }

        const input =
            document.getElementById(inputId);

        if (!input) {
            return;
        }

        const prototype =
            input instanceof HTMLTextAreaElement
                ? HTMLTextAreaElement.prototype
                : HTMLInputElement.prototype;

        const descriptor =
            Object.getOwnPropertyDescriptor(
                prototype,
                'value'
            );

        if (descriptor?.set) {
            descriptor.set.call(
                input,
                String(value ?? '')
            );
        } else {
            input.value =
                String(value ?? '');
        }

        input.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true,
                }
            )
        );

        input.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true,
                }
            )
        );

        if (triggerBlur) {
            input.dispatchEvent(
                new Event(
                    'blur',
                    {
                        bubbles: true,
                    }
                )
            );
        }
    }


    function readCoordinate(inputId) {
        if (!inputId) {
            return null;
        }

        const input =
            document.getElementById(inputId);

        if (!input) {
            return null;
        }

        const value =
            Number.parseFloat(
                input.value
            );

        return Number.isFinite(value)
            ? value
            : null;
    }


    function getPositionNumber(
        position,
        key
    ) {
        if (!position) {
            return null;
        }

        const value =
            position[key];

        if (
            typeof value
            ===
            'function'
        ) {
            return Number(
                value.call(position)
            );
        }

        return Number(value);
    }


    window.initMadLocationPicker =
        async function (
            root,
            wire = null
        ) {

            if (
                !root
                ||
                root.dataset.initialized
                ===
                '1'
            ) {
                return;
            }


            try {

                root.dataset.initialized =
                    'loading';


                /*
                |--------------------------------------------------------------------------
                | GOOGLE MAPS LOADER
                |--------------------------------------------------------------------------
                */

                if (
                    typeof window
                        .ensureGoogleMapsApi
                    !==
                    'function'
                ) {
                    throw new Error(
                        'Google Maps loader belum tersedia.'
                    );
                }


                await window
                    .ensureGoogleMapsApi();


                /*
                |--------------------------------------------------------------------------
                | LIBRARIES
                |--------------------------------------------------------------------------
                */

                const [
                    {
                        Map
                    },
                    {
                        AdvancedMarkerElement
                    },
                    {
                        PlaceAutocompleteElement
                    },
                    {
                        Geocoder
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
                                'places'
                            ),

                        google.maps
                            .importLibrary(
                                'geocoding'
                            ),
                    ]);


                /*
                |--------------------------------------------------------------------------
                | CONFIG
                |--------------------------------------------------------------------------
                */

                const latitudeInputId =
                    root.dataset
                        .latitudeInputId;

                const longitudeInputId =
                    root.dataset
                        .longitudeInputId;

                const addressInputId =
                    root.dataset
                        .addressInputId
                    || null;


                const mapElement =
                    root.querySelector(
                        '[data-location-map]'
                    );

                const autocompleteContainer =
                    root.querySelector(
                        '[data-location-autocomplete]'
                    );

                const errorElement =
                    root.querySelector(
                        '[data-location-error]'
                    );


                /*
                |--------------------------------------------------------------------------
                | EXISTING COORDINATE
                |--------------------------------------------------------------------------
                */

                const initialLatitude =
                    readCoordinate(
                        latitudeInputId
                    );

                const initialLongitude =
                    readCoordinate(
                        longitudeInputId
                    );


                const hasInitialPosition =
                    initialLatitude
                    !==
                    null

                    &&

                    initialLongitude
                    !==
                    null;


                /*
                |--------------------------------------------------------------------------
                | DEFAULT = INDONESIA
                |--------------------------------------------------------------------------
                */

                const defaultPosition = {
                    lat:
                        -2.548926,

                    lng:
                        118.0148634,
                };


                const center =
                    hasInitialPosition
                        ? {
                            lat:
                                initialLatitude,

                            lng:
                                initialLongitude,
                        }
                        :
                        defaultPosition;


                /*
                |--------------------------------------------------------------------------
                | MAP
                |--------------------------------------------------------------------------
                */

                const map =
                    new Map(
                        mapElement,
                        {
                            center,

                            zoom:
                                hasInitialPosition
                                    ? 17
                                    : 5,

                            mapId:
                                window
                                    .__routeOptimizationMapId
                                ||
                                'DEMO_MAP_ID',

                            mapTypeControl:
                                false,

                            streetViewControl:
                                false,

                            fullscreenControl:
                                true,
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | MARKER
                |--------------------------------------------------------------------------
                */

                const markerOptions = {

                    map,

                    gmpDraggable:
                        true,

                    title:
                        'Geser marker untuk menyesuaikan lokasi',
                };


                if (
                    hasInitialPosition
                ) {
                    markerOptions.position =
                        center;
                }


                const marker =
                    new AdvancedMarkerElement(
                        markerOptions
                    );


                const geocoder =
                    new Geocoder();


                /*
                |--------------------------------------------------------------------------
                | UPDATE COORDINATE
                |--------------------------------------------------------------------------
                */

                function updateCoordinates(
                    latitude,
                    longitude
                ) {
                    if (
                        !Number.isFinite(latitude)
                        ||
                        !Number.isFinite(longitude)
                    ) {
                        return;
                    }

                    const latitudeValue =
                        latitude.toFixed(7);

                    const longitudeValue =
                        longitude.toFixed(7);

                    setInputValue(
                        latitudeInputId,
                        latitudeValue,
                        false
                    );

                    setInputValue(
                        longitudeInputId,
                        longitudeValue,
                        false
                    );


                    if (
                        wire
                        &&
                        typeof wire.$set === 'function'
                    ) {

                        wire.$set(
                            'data.latitude',
                            latitudeValue,
                            false
                        );


                        wire.$set(
                            'data.longitude',
                            longitudeValue,
                            true
                        );

                        return;
                    }

                    setInputValue(
                        latitudeInputId,
                        latitudeValue,
                        true
                    );


                    setInputValue(
                        longitudeInputId,
                        longitudeValue,
                        true
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | REVERSE GEOCODING
                |--------------------------------------------------------------------------
                |
                | Hanya update address apabila form
                | memang mempunyai addressInputId.
                |
                */

                async function updateAddress(
                    latitude,
                    longitude
                ) {
                    if (
                        !addressInputId
                    ) {
                        return;
                    }


                    try {

                        const response =
                            await geocoder
                                .geocode({
                                    location: {
                                        lat:
                                            latitude,

                                        lng:
                                            longitude,
                                    },
                                });


                        const result =
                            response
                                .results
                            ?.[0];


                        if (
                            result
                                ?.formatted_address
                        ) {
                            setInputValue(
                                addressInputId,
                                result
                                    .formatted_address
                            );
                        }

                    } catch (error) {

                        console.warn(
                            'Reverse geocoding gagal:',
                            error
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | AUTOCOMPLETE
                |--------------------------------------------------------------------------
                */

                const autocomplete =
                    new PlaceAutocompleteElement();


                autocomplete.placeholder =
                    'Cari alamat, toko, gudang, atau tempat...';


                /*
                 * Batasi hasil ke Indonesia.
                 */
                autocomplete
                    .includedRegionCodes =
                    [
                        'id'
                    ];


                autocomplete.style.width =
                    '100%';


                autocompleteContainer
                    .replaceChildren(
                        autocomplete
                    );


                autocomplete
                    .addEventListener(
                        'gmp-select',

                        async (
                            {
                                placePrediction
                            }
                        ) => {

                            const place =
                                placePrediction
                                    .toPlace();


                            await place
                                .fetchFields({
                                    fields: [
                                        'displayName',
                                        'formattedAddress',
                                        'location',
                                        'viewport',
                                    ],
                                });


                            if (
                                !place.location
                            ) {
                                return;
                            }


                            const latitude =
                                place.location
                                    .lat();

                            const longitude =
                                place.location
                                    .lng();


                            await updateCoordinates(
                                latitude,
                                longitude
                            );


                            marker.position =
                                place.location;


                            /*
                             * Untuk STORE:
                             * otomatis isi address.
                             */
                            if (
                                addressInputId
                                &&
                                place
                                    .formattedAddress
                            ) {
                                setInputValue(
                                    addressInputId,
                                    place
                                        .formattedAddress
                                );
                            }


                            if (
                                place.viewport
                            ) {
                                map.fitBounds(
                                    place.viewport
                                );
                            } else {

                                map.setCenter(
                                    place.location
                                );

                                map.setZoom(
                                    17
                                );
                            }
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | MARKER DRAG
                |--------------------------------------------------------------------------
                */

                marker.addListener(
                    'dragend',

                    async () => {

                        const position =
                            marker.position;


                        const latitude =
                            getPositionNumber(
                                position,
                                'lat'
                            );

                        const longitude =
                            getPositionNumber(
                                position,
                                'lng'
                            );


                        if (
                            !Number.isFinite(
                                latitude
                            )
                            ||
                            !Number.isFinite(
                                longitude
                            )
                        ) {
                            return;
                        }


                        await updateCoordinates(
                            latitude,
                            longitude
                        );


                        await updateAddress(
                            latitude,
                            longitude
                        );
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | CLICK MAP
                |--------------------------------------------------------------------------
                |
                | Bonus:
                | klik titik map juga memindahkan marker.
                |
                */

                map.addListener(
                    'click',

                    async event => {

                        if (
                            !event.latLng
                        ) {
                            return;
                        }


                        const latitude =
                            event
                                .latLng
                                .lat();

                        const longitude =
                            event
                                .latLng
                                .lng();


                        marker.position =
                            event.latLng;


                        await updateCoordinates(
                            latitude,
                            longitude
                        );


                        await updateAddress(
                            latitude,
                            longitude
                        );
                    }
                );


                root.dataset.initialized =
                    '1';


                if (errorElement) {
                    errorElement.hidden =
                        true;
                }

            } catch (error) {

                root.dataset.initialized =
                    '0';


                console.error(
                    'Location Picker Error:',
                    error
                );


                const errorElement =
                    root.querySelector(
                        '[data-location-error]'
                    );


                if (errorElement) {

                    errorElement.hidden =
                        false;

                    errorElement.textContent =
                        error.message
                        ||
                        'Google Maps gagal dimuat.';
                }
            }
        };
})();