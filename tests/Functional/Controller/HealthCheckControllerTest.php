<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\HealthCheckController;
use App\Services\ServiceStatusInspector\DigitalOceanMachineProviderInspector;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Entity\Droplet as DropletEntity;

class HealthCheckControllerTest extends AbstractBaseFunctionalTest
{
    public function testGet(): void
    {
        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        if ($dropletApiProxy instanceof DropletApiProxy) {
            $dropletApiProxy->withGetByIdCall(DigitalOceanMachineProviderInspector::DROPLET_ID, new DropletEntity());
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
