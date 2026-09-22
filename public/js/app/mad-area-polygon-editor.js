(function () {

    function updateInput(
        inputId,
        value
    ) {
        const input =
            document.getElementById(
                inputId
            );

        if (!input) {
            return;
        }


        const descriptor =
            Object.getOwnPropertyDescriptor(
                HTMLTextAreaElement.prototype,
                'value'
            );


        if (descriptor?.set) {

            descriptor.set.call(
                input,
                value
            );

        } else {

            input.value =
                value;
        }


        input.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles:
                        true,
                }
            )
        );


        input.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles:
                        true,
                }
            )
        );
    }


    function polygonToGeoJson(
        polygon
    ) {
        const path =
            polygon.getPath();


        const coordinates =
            [];


        for (
            let i = 0;
            i < path.getLength();
            i++
        ) {

            const point =
                path.getAt(i);


            coordinates.push([
                Number(
                    point.lng()
                        .toFixed(7)
                ),

                Number(
                    point.lat()
                        .toFixed(7)
                ),
            ]);
        }


        if (
            coordinates.length
            >=
            3
        ) {

            coordinates.push([
                coordinates[0][0],
                coordinates[0][1],
            ]);
        }


        return JSON.stringify({
            type:
                'Polygon',

            coordinates: [
                coordinates
            ],
        });
    }


    function parsePolygon(
        value
    ) {
        if (!value) {
            return [];
        }


        try {

            const geoJson =
                JSON.parse(
                    value
                );


            const geometry =
                geoJson.type
                ===
                'Feature'

                    ? geoJson.geometry

                    : geoJson;


            if (
                geometry?.type
                !==
                'Polygon'
            ) {

                return [];
            }


            const ring =
                geometry
                    .coordinates
                    ?.[0]
                || [];


            /*
             * GeoJSON menyimpan:
             * longitude, latitude
             */
            return ring
                .slice(
                    0,
                    -1
                )
                .map(
                    coordinate => ({
                        lat:
                            Number(
                                coordinate[1]
                            ),

                        lng:
                            Number(
                                coordinate[0]
                            ),
                    })
                );

        } catch (error) {

            console.warn(
                'Polygon GeoJSON tidak valid.',
                error
            );

            return [];
        }
    }


    window.initMadAreaPolygonEditor =
        async function (root) {

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
                    '1';


                await window
                    .ensureGoogleMapsApi();


                const [
                    {
                        Map,
                        Polygon,
                    },
                    {
                        PlaceAutocompleteElement,
                    },
                ] =
                    await Promise.all([

                        google.maps
                            .importLibrary(
                                'maps'
                            ),

                        google.maps
                            .importLibrary(
                                'places'
                            ),
                    ]);


                const inputId =
                    root.dataset
                        .polygonInputId;


                const input =
                    document
                        .getElementById(
                            inputId
                        );


                const mapElement =
                    root.querySelector(
                        '[data-area-map]'
                    );


                const searchElement =
                    root.querySelector(
                        '[data-area-search]'
                    );


                const finishButton =
                    root.querySelector(
                        '[data-area-finish]'
                    );


                const resetButton =
                    root.querySelector(
                        '[data-area-reset]'
                    );


                /*
                |--------------------------------------------------------------------------
                | EXISTING POLYGON
                |--------------------------------------------------------------------------
                */

                const existingPath =
                    parsePolygon(
                        input?.value
                    );


                let drawing =
                    existingPath.length
                    <
                    3;


                const map =
                    new Map(
                        mapElement,
                        {
                            center: {
                                lat:
                                    -2.548926,

                                lng:
                                    118.0148634,
                            },

                            zoom:
                                5,

                            mapId:
                                window
                                    .__routeOptimizationMapId
                                ||
                                'DEMO_MAP_ID',

                            streetViewControl:
                                false,

                            mapTypeControl:
                                true,
                        }
                    );


                const polygon =
                    new Polygon({
                        map,

                        paths:
                            existingPath,

                        editable:
                            !drawing,

                        draggable:
                            false,

                        strokeOpacity:
                            0.9,

                        strokeWeight:
                            3,

                        fillOpacity:
                            0.18,
                    });


                /*
                |--------------------------------------------------------------------------
                | FIT EXISTING POLYGON
                |--------------------------------------------------------------------------
                */

                if (
                    existingPath.length
                    >=
                    3
                ) {

                    const bounds =
                        new google.maps
                            .LatLngBounds();


                    existingPath
                        .forEach(
                            point =>
                                bounds.extend(
                                    point
                                )
                        );


                    map.fitBounds(
                        bounds
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SAVE POLYGON
                |--------------------------------------------------------------------------
                */

                function sync()
                {
                    if (
                        polygon
                            .getPath()
                            .getLength()
                        <
                        3
                    ) {

                        updateInput(
                            inputId,
                            ''
                        );

                        return;
                    }


                    updateInput(
                        inputId,
                        polygonToGeoJson(
                            polygon
                        )
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | DRAW BY CLICK
                |--------------------------------------------------------------------------
                */

                map.addListener(
                    'click',
                    event => {

                        if (
                            !drawing
                            ||
                            !event.latLng
                        ) {

                            return;
                        }


                        polygon
                            .getPath()
                            .push(
                                event.latLng
                            );


                        sync();
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | EDIT VERTICES
                |--------------------------------------------------------------------------
                */

                const path =
                    polygon.getPath();


                path.addListener(
                    'set_at',
                    sync
                );


                path.addListener(
                    'insert_at',
                    sync
                );


                path.addListener(
                    'remove_at',
                    sync
                );


                /*
                |--------------------------------------------------------------------------
                | FINISH
                |--------------------------------------------------------------------------
                */

                finishButton
                    ?.addEventListener(
                        'click',
                        () => {

                            if (
                                polygon
                                    .getPath()
                                    .getLength()
                                <
                                3
                            ) {

                                return;
                            }


                            drawing =
                                false;


                            polygon
                                .setEditable(
                                    true
                                );


                            sync();
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | RESET
                |--------------------------------------------------------------------------
                */

                resetButton
                    ?.addEventListener(
                        'click',
                        () => {

                            polygon
                                .setEditable(
                                    false
                                );


                            polygon
                                .setPath(
                                    []
                                );


                            drawing =
                                true;


                            updateInput(
                                inputId,
                                ''
                            );
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | SEARCH CITY / PLACE
                |--------------------------------------------------------------------------
                */

                const autocomplete =
                    new PlaceAutocompleteElement();


                autocomplete.placeholder =
                    'Cari kota / lokasi area...';


                autocomplete
                    .includedRegionCodes =
                    [
                        'id'
                    ];


                searchElement
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
                                        'location',
                                        'viewport',
                                    ],
                                });


                            if (
                                place.viewport
                            ) {

                                map.fitBounds(
                                    place.viewport
                                );

                            } else if (
                                place.location
                            ) {

                                map.setCenter(
                                    place.location
                                );


                                map.setZoom(
                                    13
                                );
                            }
                        }
                    );

            } catch (error) {

                console.error(
                    'Area Polygon Editor:',
                    error
                );


                const errorElement =
                    root.querySelector(
                        '[data-area-error]'
                    );


                if (
                    errorElement
                ) {

                    errorElement.hidden =
                        false;


                    errorElement.textContent =
                        error.message
                        ||
                        'Map area gagal dimuat.';
                }
            }
        };
})();