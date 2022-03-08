<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractIntegrationTest extends TestCase
{
    private Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client([
            'base_uri' => 'http://localhost:9090/',
        ]);
    }

    protected function makeRequest(string $method, string $uri, ?string $token = null): ResponseInterface
    {
        $headers = [];
        if (is_string($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->httpClient->request($method, $uri, [
            'headers' => $headers,
        ]);
    }
}
