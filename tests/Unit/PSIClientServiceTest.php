<?php

namespace Tests\Unit;

use Tests\TestCase;
use Apogee\Watcher\Services\PSIClientService;
use Apogee\Watcher\Services\RateLimitService;
use Apogee\Watcher\Exceptions\MissingApiKeyException;
use GuzzleHttp\Client as GuzzleClient;

class PSIClientServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set required configuration
        config(['watcher.api_daily_limit' => 25000]);
        config(['watcher.psi_cost_per_request' => 0.002]);
    }

    /**
     * Test that the testPage method returns success result for valid requests.
     * 
     * Verifies that when a valid API request is made, the method returns
     * the correct HTTP code, performance score, and no error.
     */
    public function test_test_page_returns_success_result(): void
    {
        // Create a partial mock of PSIClientService that mocks the runTest method
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                'test_key',
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willReturn([
                'lighthouseResult' => [
                    'categories' => [
                        'performance' => ['score' => 0.85],
                    ],
                ],
            ]);

        $result = $service->testPage('https://example.com', 'mobile');

        $this->assertEquals(200, $result['http_code']);
        $this->assertEquals(85, $result['score']);
        $this->assertNull($result['error']);
    }

    /**
     * Test that the testPage method handles missing API key correctly.
     * 
     * Verifies that when no API key is configured, the method returns
     * the appropriate error code and message.
     */
    public function test_test_page_handles_missing_api_key(): void
    {
        // Create a partial mock that will throw MissingApiKeyException
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                null, // No API key
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willThrowException(new MissingApiKeyException());

        $result = $service->testPage('https://example.com', 'mobile');

        $this->assertEquals(401, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertNotNull($result['error']);
    }

    /**
     * Test that the testPage method handles rate limit errors correctly.
     * 
     * Verifies that when rate limit is exceeded, the method returns
     * the appropriate error code and message.
     */
    public function test_test_page_handles_rate_limit_error(): void
    {
        // Create a partial mock that will throw runtime exception with rate limit message
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                'test_key',
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willThrowException(new \RuntimeException('quota exceeded or rate limited (429)'));

        $result = $service->testPage('https://example.com', 'mobile');

        $this->assertEquals(429, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertStringContains('429', $result['error']);
    }

    /**
     * Test that the testPage method handles server errors correctly.
     * 
     * Verifies that when a server error occurs, the method returns
     * the appropriate error code and message.
     */
    public function test_test_page_handles_server_error(): void
    {
        // Create a partial mock that will throw runtime exception with server error message
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                'test_key',
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willThrowException(new \RuntimeException('PSI server error (5xx) — retry later'));

        $result = $service->testPage('https://example.com', 'mobile');

        $this->assertEquals(502, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertStringContains('5xx', $result['error']);
    }

    /**
     * Test that the testPage method handles invalid arguments correctly.
     * 
     * Verifies that when invalid arguments are provided, the method returns
     * the appropriate error code and message.
     */
    public function test_test_page_handles_invalid_arguments(): void
    {
        // Create a partial mock that will throw InvalidArgumentException
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                'test_key',
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willThrowException(new \InvalidArgumentException('Invalid URL format'));

        $result = $service->testPage('invalid-url', 'mobile');

        $this->assertEquals(400, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertStringContains('Invalid URL format', $result['error']);
    }
}