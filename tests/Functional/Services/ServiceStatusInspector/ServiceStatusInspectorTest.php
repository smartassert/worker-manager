<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\DigitalOceanMachineProviderInspector;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\RuntimeException;
use SmartAssert\ServiceStatusInspector\ServiceStatusInspector;
use SmartAssert\ServiceStatusInspector\ServiceStatusInspectorInterface;
use webignition\ObjectReflector\ObjectReflector;

class ServiceStatusInspectorTest extends AbstractBaseFunctionalTest
{
    private ServiceStatusInspector $serviceStatusInspector;

    protected function setUp(): void
    {
        parent::setUp();

        $serviceStatusInspector = self::getContainer()->get(ServiceStatusInspectorInterface::class);
        \assert($serviceStatusInspector instanceof ServiceStatusInspector);
        $this->serviceStatusInspector = $serviceStatusInspector;
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param callable[]          $modifiedComponentInspectors
     * @param array<string, bool> $expectedServiceStatus
     */
    public function testGet(
        \Exception | DropletEntity $dropletApiGetByIdOutcome,
        array $modifiedComponentInspectors,
        array $expectedServiceStatus
    ): void {
        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        if ($dropletApiProxy instanceof DropletApiProxy) {
            $dropletApiProxy->withGetByIdCall(
                DigitalOceanMachineProviderInspector::DROPLET_ID,
                $dropletApiGetByIdOutcome
            );
        }

        foreach ($modifiedComponentInspectors as $name => $componentInspector) {
            $this->setComponentInspector($name, $componentInspector);
        }

        self::assertEquals($expectedServiceStatus, $this->serviceStatusInspector->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'all services available' => [
                'dropletApiGetByIdOutcome' => new DropletEntity(),
                'modifiedComponentInspectors' => [],
                'expectedServiceStatus' => [
                    'database_connection' => true,
                    'database_entities' => true,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'database unavailable' => [
                'dropletApiGetByIdOutcome' => new DropletEntity(),
                'modifiedComponentInspectors' => [
                    'database_connection' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database_connection' => false,
                    'database_entities' => true,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'message queue unavailable' => [
                'dropletApiGetByIdOutcome' => new DropletEntity(),
                'modifiedComponentInspectors' => [
                    'message_queue' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database_connection' => true,
                    'database_entities' => true,
                    'message_queue' => false,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'digital ocean machine provider unavailable' => [
                'dropletApiGetByIdOutcome' => new RuntimeException('Unauthorized', 401),
                'modifiedComponentInspectors' => [],
                'expectedServiceStatus' => [
                    'database_connection' => true,
                    'database_entities' => true,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => false,
                ],
            ],
            'all services unavailable' => [
                'dropletApiGetByIdOutcome' => new RuntimeException('Unauthorized', 401),
                'modifiedComponentInspectors' => [
                    'database_connection' => $this->createComponentInspectorThrowingException(),
                    'message_queue' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database_connection' => false,
                    'database_entities' => true,
                    'message_queue' => false,
                    'machine_provider_digital_ocean' => false,
                ],
            ],
        ];
    }

    private function setComponentInspector(string $name, callable $componentInspector): void
    {
        $componentInspectors = ObjectReflector::getProperty(
            $this->serviceStatusInspector,
            'componentInspectors',
            ServiceStatusInspector::class
        );
        self::assertIsArray($componentInspectors);

        if (array_key_exists($name, $componentInspectors)) {
            $componentInspectors[$name] = $componentInspector;
        }

        ObjectReflector::setProperty(
            $this->serviceStatusInspector,
            ServiceStatusInspector::class,
            'componentInspectors',
            $componentInspectors
        );
    }

    private function createComponentInspectorThrowingException(): callable
    {
        return function () {
            throw new \Exception();
        };
    }
}
