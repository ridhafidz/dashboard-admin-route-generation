@once

<script>

window.__routeOptimizationMapsKey =
    @js(
        config(
            'services.google_maps.browser_key',
            config('services.google_maps.key')
        )
    );


window.__routeOptimizationMapId =
    @js(
        config(
            'services.google_maps.map_id',
            'DEMO_MAP_ID'
        )
    );


window.ensureGoogleMapsApi =
    window.ensureGoogleMapsApi
    ||
    function ()
    {
        /*
        |--------------------------------------------------------------------------
        | API SUDAH TERSEDIA
        |--------------------------------------------------------------------------
        */

        if (
            window.google
            &&
            window.google.maps
            &&
            typeof window.google.maps.importLibrary
                === 'function'
        ) {
            return Promise.resolve(
                window.google.maps
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SEDANG LOADING
        |--------------------------------------------------------------------------
        */

        if (
            window.__routeOptimizationMapsPromise
        ) {
            return window
                .__routeOptimizationMapsPromise;
        }


        /*
        |--------------------------------------------------------------------------
        | LOAD API SEKALI SAJA
        |--------------------------------------------------------------------------
        */

        window.__routeOptimizationMapsPromise =
            new Promise(
                (
                    resolve,
                    reject
                ) => {

                    const key =
                        window
                            .__routeOptimizationMapsKey;


                    if (!key) {

                        reject(
                            new Error(
                                'Google Maps Browser API Key belum dikonfigurasi.'
                            )
                        );

                        return;
                    }


                    const callbackName =
                        '__routeOptimizationMapsReady';


                    window[
                        callbackName
                    ] =
                        function ()
                        {
                            if (
                                window.google
                                &&
                                window.google.maps
                                &&
                                typeof
                                    window.google
                                        .maps
                                        .importLibrary
                                    ===
                                    'function'
                            ) {

                                resolve(
                                    window.google.maps
                                );

                                return;
                            }


                            reject(
                                new Error(
                                    'Google Maps berhasil dimuat tetapi importLibrary() tidak tersedia.'
                                )
                            );
                        };


                    /*
                     * Jangan masukkan script kedua.
                     */
                    const existing =
                        document.querySelector(
                            'script[data-route-optimization-google-maps="1"]'
                        );


                    if (existing) {
                        return;
                    }


                    const script =
                        document.createElement(
                            'script'
                        );


                    script.dataset
                        .routeOptimizationGoogleMaps =
                        '1';


                    script.async =
                        true;


                    script.defer =
                        true;


                    script.src =
                        'https://maps.googleapis.com/maps/api/js'
                        + '?key='
                        + encodeURIComponent(
                            key
                        )
                        + '&v=weekly'
                        + '&loading=async'
                        + '&callback='
                        + callbackName;


                    script.onerror =
                        function ()
                        {
                            reject(
                                new Error(
                                    'Gagal memuat Google Maps JavaScript API.'
                                )
                            );
                        };


                    document
                        .head
                        .appendChild(
                            script
                        );
                }
            );


        return window
            .__routeOptimizationMapsPromise;
    };

</script>

@endonce