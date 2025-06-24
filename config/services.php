<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'open-meteo' => [
        'weather_url' => env('OPEN_METEO_WEATHER_URL', 'https://api.open-meteo.com/v1/forecast'),
        'geocoding_url' => env('OPEN_METEO_GEOCODING_URL', 'https://geocoding-api.open-meteo.com/v1/search')
    ]
];
