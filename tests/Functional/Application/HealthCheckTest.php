<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Services\ServiceStatusInspector\DigitalOceanMachineProviderInspector;
use App\Tests\Application\AbstractHealthCheckTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Entity\Droplet as DropletEntity;

class HealthCheckTest extends AbstractHealthCheckTest
{
    use GetApplicationClientTrait;

    protected function getHealthCheckSetup(): void
    {
        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        if ($dropletApiProxy instanceof DropletApiProxy) {
            $dropletApiProxy->withGetByIdCall(DigitalOceanMachineProviderInspector::DROPLET_ID, new DropletEntity());
        }
    }
}
