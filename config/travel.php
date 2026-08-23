<?php

return [
    'default_provider' => env('TRAVEL_PROVIDER', 'manual'),

    'providers' => [
        'manual' => [
            'enabled' => true,
        ],
        'travelpayouts' => [
            'enabled' => (bool) env('TRAVELPAYOUTS_ENABLED', false),
            'api_token' => env('TRAVELPAYOUTS_API_TOKEN'),
            'marker' => env('TRAVELPAYOUTS_MARKER'),
        ],
    ],
];
