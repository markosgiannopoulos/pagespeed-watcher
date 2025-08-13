<?php

namespace Apogee\Watcher\Services;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use InvalidArgumentException;
use Apogee\Watcher\Services\RateLimitService;
use Apogee\Watcher\Models\WatcherApiUsage;
use Apogee\Watcher\Exceptions\MissingApiKeyException;

class PSIClientService
{
    private const PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    private GuzzleClient $httpClient;

    private ?string $apiKey;

    private RateLimitService $rateLimitService;

    private const VALID_STRATEGIES = ['mobile', 'desktop'];

    /**
     * Create a new PSI client service instance.
     * 
     * @param GuzzleClient $httpClient The HTTP client for making API requests
     * @param string|null $apiKey The Google PageSpeed Insights API key
     * @param RateLimitService $rateLimitService The rate limiting service
     */
    public function __construct(GuzzleClient $httpClient, ?string $apiKey, RateLimitService $rateLimitService)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Run a PageSpeed Insights test for the given URL and strategy.
     * 
     * Makes an API request to Google PageSpeed Insights and returns the raw response.
     * This method handles error responses, rate limiting, and usage tracking.
     *
     * @param string $url The URL to test (must be a valid HTTP/HTTPS URL)
     * @param string $strategy The testing strategy: "mobile" or "desktop"
     * @return array The decoded JSON response from the PSI API
     * @throws InvalidArgumentException When URL is invalid or strategy is unsupported
     * @throws MissingApiKeyException When API key is not configured
     * @throws \RuntimeException When API returns an error response
     * @throws GuzzleException When HTTP request fails
     */
    public function runTest(string $url, string $strategy = 'mobile'): array
    {
        // Validate inputs
        if (empty($url)) {
            throw new InvalidArgumentException('URL cannot be empty');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid URL format');
        }

        // Enforce same host if enabled
        $enforceSameHost = (bool) config('watcher.enforce_same_host', true);
        if ($enforceSameHost) {
            $appUrl = config('app.url') ?: env('APP_URL');
            if (!empty($appUrl) && filter_var($appUrl, FILTER_VALIDATE_URL)) {
                $appHost = parse_url($appUrl, PHP_URL_HOST);
                $targetHost = parse_url($url, PHP_URL_HOST);
                if ($appHost && $targetHost && strcasecmp($appHost, $targetHost) !== 0) {
                    throw new InvalidArgumentException('URL host must match APP_URL host');
                }
            }
        }

        if (!in_array($strategy, self::VALID_STRATEGIES, true)) {
            throw new InvalidArgumentException('Strategy must be "mobile" or "desktop"');
        }

        // Check if API key is configured
        if (empty($this->apiKey)) {
            throw new MissingApiKeyException();
        }

        // Check rate limits before making request
        if (!$this->rateLimitService->canMakeRequest()) {
            throw new \RuntimeException('Rate limit exceeded. Please try again later.');
        }

        $query = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
        ];

        $query['key'] = $this->apiKey;

        try {
            $response = $this->httpClient->request('GET', self::PSI_ENDPOINT, [
                'query' => $query,
                'http_errors' => true,
            ]);

            $json = json_decode((string) $response->getBody(), true);
            if (!is_array($json)) {
                throw new \RuntimeException('Invalid JSON from PSI API');
            }

            if (isset($json['error'])) {
                $message = $json['error']['message'] ?? 'Unknown PSI API error';
                $code = $json['error']['code'] ?? 0;
                
                // Record error in usage tracking
                WatcherApiUsage::getTodayRecord()->incrementRequests(false);
                
                // Provide more specific error messages
                switch ($code) {
                    case 400:
                        throw new \RuntimeException("Bad request: {$message}");
                    case 403:
                        throw new \RuntimeException("API key error: {$message}");
                    case 429:
                        throw new \RuntimeException("quota exceeded or rate limited (429)");
                    default:
                        throw new \RuntimeException("PSI API error ({$code}): {$message}");
                }
            }

            // Record successful request for rate limiting and usage tracking
            $this->rateLimitService->recordRequest();
            WatcherApiUsage::getTodayRecord()->incrementRequests(true);

            return $json;
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            
            // Record error in usage tracking
            WatcherApiUsage::getTodayRecord()->incrementRequests(false);
            
            if ($statusCode === 429) {
                throw new \RuntimeException('quota exceeded or rate limited (429)');
            }
            
            throw new \RuntimeException("PSI API error ({$statusCode}): " . $e->getMessage());
        } catch (ServerException $e) {
            // Record error in usage tracking
            WatcherApiUsage::getTodayRecord()->incrementRequests(false);
            
            throw new \RuntimeException('PSI server error (5xx) — retry later');
        } catch (GuzzleException $e) {
            // Record error in usage tracking
            WatcherApiUsage::getTodayRecord()->incrementRequests(false);
            
            throw $e;
        }
    }

    /**
     * Extract core performance metrics from a PageSpeed Insights response.
     * 
     * Parses the Lighthouse result data to extract key performance indicators:
     * - Performance score (0-1 scale)
     * - Largest Contentful Paint (LCP) in milliseconds
     * - Interaction to Next Paint (INP) in milliseconds  
     * - Cumulative Layout Shift (CLS) as a decimal value
     * - First Contentful Paint (FCP) in milliseconds
     * - Time to First Byte (TTFB) in milliseconds
     * - First Input Delay (FID) in milliseconds
     * 
     * @param array $psiResponse The raw response from the PageSpeed Insights API
     * @return array Array containing extracted metrics with keys: score, lcp, inp, cls, fcp, ttfb, fid
     */
    public function extractCoreMetrics(array $psiResponse): array
    {
        $lighthouse = $psiResponse['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $categories = $lighthouse['categories'] ?? [];
        $performance = $categories['performance'] ?? [];

        $metrics = [
            'score' => $performance['score'] ?? null,
            'lcp' => $audits['largest-contentful-paint']['numericValue'] ?? null,
            'inp' => $audits['interaction-to-next-paint']['numericValue'] ?? null,
            'cls' => $audits['cumulative-layout-shift']['numericValue'] ?? null,
        ];

        // Add additional useful metrics
        $metrics['fcp'] = $audits['first-contentful-paint']['numericValue'] ?? null;
        $metrics['ttfb'] = $audits['server-response-time']['numericValue'] ?? null;
        $metrics['fid'] = $audits['max-potential-fid']['numericValue'] ?? null;

        return $metrics;
    }

    /**
     * Extract detailed performance insights and recommendations from PSI response.
     * 
     * Analyzes the Lighthouse audits to identify critical performance issues
     * and provides actionable recommendations for improvement.
     * 
     * @param array $psiResponse The raw response from the PageSpeed Insights API
     * @return array Array of critical issues with type, name, title, and description
     */
    public function extractPerformanceInsights(array $psiResponse): array
    {
        $lighthouse = $psiResponse['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        
        $insights = [];
        
        // Check for critical issues
        foreach ($audits as $auditName => $audit) {
            if (($audit['score'] ?? 1) < 0.5 && ($audit['details'] ?? null)) {
                $insights[] = [
                    'type' => 'critical',
                    'name' => $auditName,
                    'title' => $audit['title'] ?? $auditName,
                    'description' => $audit['description'] ?? '',
                ];
            }
        }
        
        return $insights;
    }

    /**
     * Test API connectivity and validate the configured API key.
     * 
     * Performs a test request to the PageSpeed Insights API and returns
     * a structured result with HTTP status, performance score, and any errors.
     * This method is used by the CLI command to validate API configuration.
     *
     * @param string $url The URL to test (must be a valid HTTP/HTTPS URL)
     * @param string $strategy The testing strategy: "mobile" or "desktop"
     * @return array Result array with keys: http_code, score, error
     */
    public function testApiKey(string $url, string $strategy = 'mobile'): array
    {
        try {
            $response = $this->runTest($url, $strategy);
            $metrics = $this->extractCoreMetrics($response);
            $score = isset($metrics['score']) ? (int) round($metrics['score'] * 100) : null;
            
            return [
                'http_code' => 200,
                'score' => $score,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $httpCode = 500;
            $error = $e->getMessage();
            
            if ($e instanceof MissingApiKeyException) {
                $httpCode = 401;
            } elseif ($e instanceof InvalidArgumentException) {
                $httpCode = 400;
            } elseif (str_contains($error, '429')) {
                $httpCode = 429;
            } elseif (str_contains($error, '5xx')) {
                $httpCode = 502;
            }
            
            return [
                'http_code' => $httpCode,
                'score' => null,
                'error' => $error,
            ];
        }
    }
}