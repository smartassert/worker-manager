<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\StatusController;

class StatusTest extends AbstractIntegrationTest
{
    public function testStatus(): void
    {
        $expectedVersion = $_SERVER['EXPECTED_VERSION'] ?? 'docker_compose_version';

        $response = $this->httpClient->get(StatusController::ROUTE);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame(
            [
                'version' => $expectedVersion,
                'ready' => false,
            ],
            json_decode($response->getBody()->getContents(), true)
        );
    }
}
