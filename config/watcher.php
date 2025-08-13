<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PageSpeed Insights API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Google PageSpeed Insights API key and related settings.
    |
    */
    
    'psi_api_key'        => env('PSI_API_KEY'),
    'api_daily_limit'    => env('API_DAILY_LIMIT', 25000),
    'default_timezone'   => env('DEFAULT_TIMEZONE', 'Europe/Luxembourg'),
    'daily_test_time'    => env('DAILY_TEST_TIME', '07:00'),
    
    /*
    |--------------------------------------------------------------------------
    | URL Discovery Settings
    |--------------------------------------------------------------------------
    |
    | Configure how URLs are discovered and tested.
    |
    */
    
    'discovery' => [
        'max_urls' => env('DISCOVERY_MAX_URLS', 100),
        'max_depth'=> env('DISCOVERY_MAX_DEPTH', 3),
        'exclude_patterns' => [
            '*.pdf', '*.jpg', '*.jpeg', '*.png', '*.gif', '*.svg',
            '*.css', '*.js', '/admin/*', '/api/*',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure performance score thresholds for alerts.
    |
    */
    
    'thresholds' => [
        'excellent' => (int) env('PSI_THRESHOLD_EXCELLENT', 90),
        'good' => (int) env('PSI_THRESHOLD_GOOD', 70),
        'needs_improvement' => (int) env('PSI_THRESHOLD_NEEDS_IMPROVEMENT', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP client settings for API requests.
    |
    */
    
    'http_client' => [
        'timeout' => (int) env('PSI_HTTP_TIMEOUT', 120),
        'connect_timeout' => (int) env('PSI_CONNECT_TIMEOUT', 15),
        'retry_attempts' => (int) env('PSI_RETRY_ATTEMPTS', 3),
        'retry_delay' => (int) env('PSI_RETRY_DELAY', 5),
    ],
];