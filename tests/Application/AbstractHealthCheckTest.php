<?php

declare(strict_types=1);

namespace App\Tests\Application;

use PHPUnit\Framework\Assert;

abstract class AbstractHealthCheckTest extends AbstractApplicationTest
{
    public function testGetHealthCheck(): void
    {
        $this->getHealthCheckSetup();

        $response = $this->applicationClient->makeGetHealthCheckRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        $expectedResponseData = [
            'database_connection' => true,
            'database_entities' => true,
            'message_queue' => true,
            'machine_provider_digital_ocean' => true,
        ];

        Assert::assertEquals($expectedResponseData, $responseData);
    }

    abstract protected function getHealthCheckSetup(): void;
}
