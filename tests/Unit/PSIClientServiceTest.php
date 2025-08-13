<?php

namespace Apogee\Watcher\Tests\Unit;

use Apogee\Watcher\Services\PSIClientService;
use Apogee\Watcher\Services\RateLimitService;
use Apogee\Watcher\Exceptions\MissingApiKeyException;
use Apogee\Watcher\Models\WatcherApiUsage;
use Orchestra\Testbench\TestCase;
use GuzzleHttp\Client as GuzzleClient;

class PSIClientServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // We'll handle the WatcherApiUsage mocking in each individual test
        // since the static method calls are causing issues
    }

    /**
     * Test that the testApiKey method returns success result for valid requests.
     * 
     * Verifies that when a valid API request is made, the method returns
     * the correct HTTP code, performance score, and no error.
     */
    public function test_test_api_key_returns_success_result(): void
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

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(200, $result['http_code']);
        $this->assertEquals(85, $result['score']);
        $this->assertNull($result['error']);
    }

    /**
     * Test that the testApiKey method handles missing API key correctly.
     * 
     * Verifies that when no API key is configured, the method returns
     * the appropriate error code and message.
     */
    public function test_test_api_key_throws_missing_key_exception(): void
    {
        // Create a partial mock of PSIClientService that mocks the runTest method
        $service = $this->getMockBuilder(PSIClientService::class)
            ->setConstructorArgs([
                $this->createMock(GuzzleClient::class),
                null,
                $this->createMock(RateLimitService::class)
            ])
            ->onlyMethods(['runTest'])
            ->getMock();
        
        $service->method('runTest')
            ->willThrowException(new MissingApiKeyException());

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(401, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('PSI API key is not configured', $result['error']);
    }

    /**
     * Test that the testApiKey method handles rate limit errors correctly.
     * 
     * Verifies that when the API returns a 429 rate limit error,
     * the method returns the appropriate error code and message.
     */
    public function test_test_api_key_handles_rate_limit_error(): void
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
            ->willThrowException(new \RuntimeException('quota exceeded or rate limited (429)'));

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(429, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('quota exceeded or rate limited (429)', $result['error']);
    }

    /**
     * Test that the testApiKey method handles server errors correctly.
     * 
     * Verifies that when the API returns a 5xx server error,
     * the method returns the appropriate error code and message.
     */
    public function test_test_api_key_handles_server_error(): void
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
            ->willThrowException(new \RuntimeException('PSI server error (5xx) — retry later'));

        $result = $service->testApiKey('https://example.com', 'mobile');

        $this->assertEquals(502, $result['http_code']);
        $this->assertNull($result['score']);
        $this->assertEquals('PSI server error (5xx) — retry later', $result['error']);
    }
}