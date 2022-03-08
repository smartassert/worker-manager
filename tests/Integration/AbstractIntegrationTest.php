<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

abstract class AbstractIntegrationTest extends TestCase
{
    protected Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client([
            'base_uri' => 'http://localhost:9090/',
            'headers' => [
                'Authorization' => 'Bearer valid-token',
            ],
        ]);
    }
}
