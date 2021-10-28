<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\HealthCheckController;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet;
use GuzzleHttp\Handler\MockHandler;

class HealthCheckControllerTest extends AbstractBaseFunctionalTest
{
    public function testGet(): void
    {
        $mockHandler = self::$container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $mockHandler->append(HttpResponseFactory::fromDropletEntity(new Droplet()));
        }

        $this->client->request('GET', HealthCheckController::ROUTE);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'database_connection' => true,
                'database_entities' => true,
                'message_queue' => true,
                'machine_provider_digital_ocean' => true,
            ],
            json_decode((string) $response->getContent(), true)
        );
    }
}
