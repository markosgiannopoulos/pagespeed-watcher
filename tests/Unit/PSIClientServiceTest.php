<?php

namespace Apogee\Watcher\Tests\Unit;

use Apogee\Watcher\Services\PSIClientService;
use Apogee\Watcher\Exceptions\MissingApiKeyException;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Client as GuzzleClient;

class PSIClientServiceTest extends TestCase
{
    public function test_test_api_key_returns_success_result(): void
    {
        $mockClient = $this->createMock(GuzzleClient::class);
        $mockClient->method('request')
            ->willReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'lighthouseResult' => [
                    'categories' => [
                        'performance' => ['score' => 0.85],
                    ],
                ],
            ])));

        $service = new PSIClientService($mockClient, 'test_key');

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(200, $result['http_code']);
        $this->assertEquals(85, $result['score']);
        $this->assertNull($result['error']);
    }

    public function test_test_api_key_throws_missing_key_exception(): void
    {
        $mockClient = $this->createMock(GuzzleClient::class);
        $service = new PSIClientService($mockClient, null);

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(401, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('PSI API key is not configured', $result['error']);
    }

    public function test_test_api_key_handles_rate_limit_error(): void
    {
        $mockClient = $this->createMock(GuzzleClient::class);
        $mockClient->method('request')
            ->willThrowException(new \GuzzleHttp\Exception\ClientException(
                'Rate limit exceeded',
                new \GuzzleHttp\Psr7\Request('GET', 'test'),
                new \GuzzleHttp\Psr7\Response(429)
            ));

        $service = new PSIClientService($mockClient, 'test_key');

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(429, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('quota exceeded or rate limited (429)', $result['error']);
    }

    public function test_test_api_key_handles_server_error(): void
    {
        $mockClient = $this->createMock(GuzzleClient::class);
        $mockClient->method('request')
            ->willThrowException(new \GuzzleHttp\Exception\ServerException(
                'Server error',
                new \GuzzleHttp\Psr7\Request('GET', 'test'),
                new \GuzzleHttp\Psr7\Response(500)
            ));

        $service = new PSIClientService($mockClient, 'test_key');

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(502, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('PSI server error (5xx) — retry later', $result['error']);
    }
}