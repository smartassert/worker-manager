<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractHealthCheckTestCase extends AbstractApplicationTestCase
{
    public function testGetHealthCheck(): void
    {
        $this->getHealthCheckSetup();

        $response = $this->applicationClient->makeGetHealthCheckRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'database_connection' => true,
                'database_entities' => true,
                'message_queue' => true,
                'machine_provider_digital_ocean' => true,
            ]),
            $response->getBody()->getContents()
        );
    }

    abstract protected function getHealthCheckSetup(): void;
}
