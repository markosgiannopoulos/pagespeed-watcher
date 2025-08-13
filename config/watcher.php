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
    
    'psi_api_key' => env('PSI_API_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | API Limits and Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure API usage limits and rate limiting settings.
    |
    */
    
    'api_daily_limit' => (int) env('API_DAILY_LIMIT', 25000),
    'rate_limit_per_minute' => (int) env('PSI_RATE_LIMIT_PER_MINUTE', 10),
    
    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure when and how often to run PageSpeed tests.
    |
    */
    
    'default_timezone' => env('APP_TIMEZONE', 'UTC'),
    'daily_test_time' => env('DAILY_TEST_TIME', '07:00'),
    'test_interval_hours' => env('TEST_INTERVAL_HOURS', 24),
    
    /*
    |--------------------------------------------------------------------------
    | URL Discovery Settings
    |--------------------------------------------------------------------------
    |
    | Configure how URLs are discovered and tested.
    |
    */
    
    'discovery' => [
        'max_urls' => (int) env('DISCOVERY_MAX_URLS', 100),
        'max_depth' => (int) env('DISCOVERY_MAX_DEPTH', 3),
        'exclude_patterns' => [
            '*.pdf',
            '*.jpg', '*.jpeg', '*.png', '*.gif', '*.svg',
            '*.css', '*.js',
            '/admin/*',
            '/api/*',
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