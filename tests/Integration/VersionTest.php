<?php

declare(strict_types=1);

namespace App\Tests\Integration;

class VersionTest extends AbstractIntegrationTest
{
    public function testVersion(): void
    {
        $expectedVersion = $_SERVER['EXPECTED_VERSION'] ?? 'docker_compose_version';

        $response = $this->httpClient->get('/version');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expectedVersion, $response->getBody()->getContents());
    }
}
