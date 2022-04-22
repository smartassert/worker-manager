<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

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

        $healthCheckUrl = self::getContainer()->getParameter('health_check_bundle_health_check_path');
        self::assertIsString($healthCheckUrl);

        $this->client->request('GET', $healthCheckUrl);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals(
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
