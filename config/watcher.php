<?php

return [
    'psi_api_key' => env('PSI_API_KEY'),
    'api_daily_limit' => env('API_DAILY_LIMIT', 25000),
    'default_timezone' => env('DEFAULT_TIMEZONE', 'Europe/Luxembourg'),
    'daily_test_time' => env('DAILY_TEST_TIME', '07:00'),
    'discovery' => [
        'max_urls' => env('DISCOVERY_MAX_URLS', 100),
        'max_depth' => env('DISCOVERY_MAX_DEPTH', 3),
    ],
];