<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\ServiceStatusInspector;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use webignition\ObjectReflector\ObjectReflector;

class ServiceStatusInspectorTest extends AbstractBaseFunctionalTest
{
    private ServiceStatusInspector $serviceStatusInspector;

    protected function setUp(): void
    {
        parent::setUp();

        $serviceStatusInspector = self::$container->get(ServiceStatusInspector::class);
        \assert($serviceStatusInspector instanceof ServiceStatusInspector);
        $this->serviceStatusInspector = $serviceStatusInspector;
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param ResponseInterface[] $httpFixtures
     * @param callable[]          $modifiedComponentInspectors
     * @param array<string, bool> $expectedServiceStatus
     */
    public function testGet(
        array $httpFixtures,
        array $modifiedComponentInspectors,
        array $expectedServiceStatus
    ): void {
        $mockHandler = self::$container->get(MockHandler::class);
        if ($mockHandler instanceof MockHandler) {
            $mockHandler->append(...$httpFixtures);
        }

        foreach ($modifiedComponentInspectors as $name => $componentInspector) {
            $this->setComponentInspector($name, $componentInspector);
        }

        self::assertEquals($expectedServiceStatus, $this->serviceStatusInspector->get());
    }

    /**
     * @return array[]
     */
    public function getDataProvider(): array
    {
        return [
            'all services available' => [
                'httpFixtures' => [
                    $this->createDigitalOceanDropletResponse(),
                ],
                'modifiedComponentInspectors' => [],
                'expectedServiceStatus' => [
                    'database' => true,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'database unavailable' => [
                'httpFixtures' => [
                    $this->createDigitalOceanDropletResponse(),
                ],
                'modifiedComponentInspectors' => [
                    'database' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database' => false,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'message queue unavailable' => [
                'httpFixtures' => [
                    $this->createDigitalOceanDropletResponse(),
                ],
                'modifiedComponentInspectors' => [
                    'message_queue' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database' => true,
                    'message_queue' => false,
                    'machine_provider_digital_ocean' => true,
                ],
            ],
            'digital ocean machine provider unavailable' => [
                'httpFixtures' => [
                    new Response(401),
                ],
                'modifiedComponentInspectors' => [],
                'expectedServiceStatus' => [
                    'database' => true,
                    'message_queue' => true,
                    'machine_provider_digital_ocean' => false,
                ],
            ],
            'all services unavailable' => [
                'httpFixtures' => [
                    new Response(401),
                ],
                'modifiedComponentInspectors' => [
                    'database' => $this->createComponentInspectorThrowingException(),
                    'message_queue' => $this->createComponentInspectorThrowingException(),
                ],
                'expectedServiceStatus' => [
                    'database' => false,
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

    private function createDigitalOceanDropletResponse(): ResponseInterface
    {
        return HttpResponseFactory::fromDropletEntity(new DropletEntity());
    }
}
