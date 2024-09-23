<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractHealthCheckTestCase extends AbstractApplicationTestCase
{
    public function testGetHealthCheck(): void
    {
        $this->getHealthCheckSetup();

        $this->jsonResponseAsserter->assertJsonResponse(
            $this->applicationClient->makeGetHealthCheckRequest(),
            200,
            [
                'database_connection' => true,
                'database_entities' => true,
                'message_queue' => true,
                'machine_provider_digital_ocean' => true,
            ]
        );
    }

    abstract protected function getHealthCheckSetup(): void;
}
