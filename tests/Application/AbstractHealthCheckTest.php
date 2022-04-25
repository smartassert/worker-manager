<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractHealthCheckTest extends AbstractApplicationTest
{
    public function testGetHealthCheck(): void
    {
        $this->getHealthCheckSetup();

        $this->responseAsserter->assertHealthCheckResponse(
            $this->applicationClient->makeGetHealthCheckRequest()
        );
    }

    abstract protected function getHealthCheckSetup(): void;
}
