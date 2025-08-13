<?php

namespace Apogee\Watcher\Services;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class PSIClientService
{
    private GuzzleClient $httpClient;

    private ?string $apiKey;

    public function __construct(GuzzleClient $httpClient, ?string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    /**
     * Run a PSI test for the given URL and strategy.
     *
     * @param string $url
     * @param string $strategy "mobile" or "desktop"
     * @return array Decoded JSON response
     * @throws GuzzleException
     */
    public function runTest(string $url, string $strategy = 'mobile'): array
    {
        $query = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
        ];

        if (!empty($this->apiKey)) {
            $query['key'] = $this->apiKey;
        }

        $response = $this->httpClient->request('GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed', [
            'query' => $query,
            'http_errors' => true,
            'timeout' => 120,
            'connect_timeout' => 15,
        ]);

        $json = json_decode((string) $response->getBody(), true);
        if (!is_array($json)) {
            throw new \RuntimeException('Invalid JSON from PSI API');
        }

        if (isset($json['error'])) {
            $message = $json['error']['message'] ?? 'Unknown PSI API error';
            throw new \RuntimeException($message);
        }

        return $json;
    }

    /**
     * Extract core metrics from a PSI response.
     * - score: 0..1
     * - lcp, inp in milliseconds
     * - cls decimal (unitless)
     */
    public function extractCoreMetrics(array $psiResponse): array
    {
        $lighthouse = $psiResponse['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];
        $categories = $lighthouse['categories'] ?? [];
        $performance = $categories['performance'] ?? [];

        return [
            'score' => $performance['score'] ?? null,
            'lcp' => $audits['largest-contentful-paint']['numericValue'] ?? null,
            'inp' => $audits['interaction-to-next-paint']['numericValue'] ?? null,
            'cls' => $audits['cumulative-layout-shift']['numericValue'] ?? null,
        ];
    }
}