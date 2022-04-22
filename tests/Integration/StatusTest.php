<?php

declare(strict_types=1);

namespace App\Tests\Integration;

class StatusTest extends AbstractIntegrationTest
{
    public function testStatus(): void
    {
        $expectedVersion = $_SERVER['EXPECTED_VERSION'] ?? 'docker_compose_version';

        $response = $this->makeRequest('GET', '/');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertEquals(
            [
                'version' => $expectedVersion,
                'ready' => false,
            ],
            json_decode($response->getBody()->getContents(), true)
        );
    }
}
