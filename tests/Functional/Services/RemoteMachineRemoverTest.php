<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\MachineNotFindableException;
use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Model\MachineActionInterface;
use App\Services\MachineNameFactory;
use App\Services\RemoteMachineRemover;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Exception\RuntimeException;

class RemoteMachineRemoverTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private RemoteMachineRemover $remover;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;

    protected function setUp(): void
    {
        parent::setUp();

        $machineManager = self::getContainer()->get(RemoteMachineRemover::class);
        \assert($machineManager instanceof RemoteMachineRemover);
        $this->remover = $machineManager;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);
    }

    /**
     * @dataProvider removeSuccessDataProvider
     */
    public function testRemoveSuccess(?\Exception $dropletApiException): void
    {
        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $dropletApiException);

        $this->expectNotToPerformAssertions();

        $this->remover->remove(self::MACHINE_ID);
    }

    /**
     * @return array<mixed>
     */
    public function removeSuccessDataProvider(): array
    {
        return [
            'removed' => [
                'exception' => null,
            ],
            'not found' => [
                'exception' => new RuntimeException('Not Found', 404),
            ],
        ];
    }

    public function testRemoveMachineNotRemovable(): void
    {
        $httpException = new RuntimeException('Service Unavailable', 503);

        $this->dropletApiProxy->withRemoveTaggedCall($this->machineName, $httpException);

        $expectedExceptionStack = [
            new HttpException(self::MACHINE_ID, MachineActionInterface::ACTION_DELETE, $httpException),
        ];

        try {
            $this->remover->remove(self::MACHINE_ID);
            self::fail(MachineNotFindableException::class . ' not thrown');
        } catch (MachineNotRemovableException $machineNotFoundException) {
            self::assertEquals($expectedExceptionStack, $machineNotFoundException->getExceptionStack());
        }
    }
}
