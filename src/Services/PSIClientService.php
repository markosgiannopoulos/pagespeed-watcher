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

    public function __construct(GuzzleClient $httpClient, ?string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->rateLimitService = new RateLimitService();
    }

    /**
     * Run a PSI test for the given URL and strategy.
     *
     * @param string $url
     * @param string $strategy "mobile" or "desktop"
     * @return array Decoded JSON response
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws \RuntimeException
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
     * Extract core metrics from a PSI response.
     * - score: 0..1
     * - lcp, inp in milliseconds
     * - cls decimal (unitless)
     * 
     * @param array $psiResponse
     * @return array
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
     * Get detailed performance insights from PSI response.
     * 
     * @param array $psiResponse
     * @return array
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
     * Test API connectivity and return result.
     *
     * @param string $url
     * @param string $strategy
     * @return array
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