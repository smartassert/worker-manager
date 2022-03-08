<?php

declare(strict_types=1);

namespace App\Tests\Integration;

class HealthCheckTest extends AbstractIntegrationTest
{
    public function testHealthCheck(): void
    {
        $response = $this->makeRequest('GET', '/health-check');

        self::assertSame(
            [
                'database_connection' => true,
                'database_entities' => true,
                'message_queue' => true,
                'machine_provider_digital_ocean' => true,
            ],
            json_decode($response->getBody()->getContents(), true)
        );
    }
}
