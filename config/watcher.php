<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PageSpeed Insights API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Google PageSpeed Insights API key and related settings.
    | You can obtain an API key from the Google Cloud Console:
    | https://console.cloud.google.com/apis/credentials
    |
    */
    
    'psi_api_key' => env('PSI_API_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | API Limits and Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure API usage limits and rate limiting settings.
    | The Google PageSpeed Insights API has a free tier of 25,000 requests per day.
    | Additional requests are charged at $0.002 per request.
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
    | These settings are used for automated testing schedules.
    |
    */
    
    'default_timezone' => env('DEFAULT_TIMEZONE', 'Europe/Luxembourg'),
    'daily_test_time' => env('DAILY_TEST_TIME', '07:00'),
    
    /*
    |--------------------------------------------------------------------------
    | URL Discovery Settings
    |--------------------------------------------------------------------------
    |
    | Configure how URLs are discovered and tested when using automated
    | URL discovery features. These settings help control the scope
    | and depth of URL crawling.
    |
    */
    
    'discovery' => [
        // Maximum number of URLs to discover and test
        'max_urls' => (int) env('DISCOVERY_MAX_URLS', 100),
        
        // Maximum depth for URL discovery (how many levels deep to crawl)
        'max_depth' => (int) env('DISCOVERY_MAX_DEPTH', 3),
        
        // File patterns to exclude from discovery
        // These patterns help avoid testing non-HTML resources
        'exclude_patterns' => [
            // Document files
            '*.pdf',
            
            // Image files
            '*.jpg', '*.jpeg', '*.png', '*.gif', '*.svg',
            
            // Asset files
            '*.css', '*.js',
            
            // Administrative areas
            '/admin/*', '/api/*',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure performance score thresholds for alerts and feedback.
    | These thresholds determine how performance scores are categorized
    | in command output and notifications.
    |
    | Scores are on a scale of 0-100, where:
    | - 90-100: Excellent
    | - 70-89:  Good
    | - 50-69:  Needs Improvement
    | - 0-49:   Poor
    |
    */
    
    'thresholds' => [
        // Minimum score to be considered "excellent" performance
        'excellent' => (int) env('PSI_THRESHOLD_EXCELLENT', 90),
        
        // Minimum score to be considered "good" performance
        'good' => (int) env('PSI_THRESHOLD_GOOD', 70),
        
        // Minimum score to be considered "needs improvement"
        'needs_improvement' => (int) env('PSI_THRESHOLD_NEEDS_IMPROVEMENT', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP client settings for API requests to Google PageSpeed Insights.
    | These settings control timeouts, retry behavior, and connection handling.
    |
    */
    
    'http_client' => [
        // Total timeout for the entire HTTP request (including response time)
        'timeout' => (int) env('PSI_HTTP_TIMEOUT', 120),
        
        // Timeout for establishing the initial connection
        'connect_timeout' => (int) env('PSI_CONNECT_TIMEOUT', 15),
        
        // Number of times to retry failed requests
        'retry_attempts' => (int) env('PSI_RETRY_ATTEMPTS', 3),
        
        // Delay in seconds between retry attempts
        'retry_delay' => (int) env('PSI_RETRY_DELAY', 5),
    ],
];