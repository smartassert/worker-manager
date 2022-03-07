<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\MessageHandler\CreateMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletProxy;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException as VendorRuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\DigitalOceanDropletConfiguration\Configuration;
use SmartAssert\DigitalOceanDropletConfiguration\Factory;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $machineStore->store($this->machine);

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderStore->store($machineProvider);
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame([], ObjectReflector::getProperty($this->machine, 'ip_addresses'));

        $dropletId = 123;

        $dropletData = [
            'id' => $dropletId,
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => '10.0.0.1',
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => '127.0.0.1',
                        'type' => 'public',
                    ],
                ],
            ],
            'status' => RemoteMachine::STATE_NEW,
        ];

        $expectedDropletEntity = new DropletEntity($dropletData);

        $dropletApiProxy = self::getContainer()->get(DropletProxy::class);
        if ($dropletApiProxy instanceof DropletProxy) {
            $dropletConfiguration = $this->createDropletConfiguration('test-worker-machine id');

            $dropletApiProxy->withCreateCall(
                'test-worker-machine id',
                $dropletConfiguration->getRegion(),
                $dropletConfiguration->getSize(),
                $dropletConfiguration->getImage(),
                $dropletConfiguration->getBackups(),
                $dropletConfiguration->getIpv6(),
                $dropletConfiguration->getVpcUuid(),
                $dropletConfiguration->getSshKeys(),
                $dropletConfiguration->getUserData(),
                $dropletConfiguration->getMonitoring(),
                $dropletConfiguration->getVolumes(),
                $dropletConfiguration->getTags(),
                $expectedDropletEntity,
            );
        }

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory)
        );

        $message = $machineRequestFactory->createCreate(self::MACHINE_ID);

        ($this->handler)($message);

        $requestIdFactory->reset(1);

        $expectedRequest = $machineRequestFactory->createCheckIsActive(self::MACHINE_ID);

        $expectedRemoteMachine = new RemoteMachine($expectedDropletEntity);

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);

        $messengerAsserter->assertQueueCount(1);
        $messengerAsserter->assertMessageAtPositionEquals(0, $expectedRequest);

        self::assertSame($expectedRemoteMachine->getState(), $this->machine->getState());
        self::assertSame(
            $expectedRemoteMachine->getIpAddresses(),
            ObjectReflector::getProperty($this->machine, 'ip_addresses')
        );
    }

    public function testInvokeUnsupportedProvider(): void
    {
        $invalidProvider = 'invalid';
        $unsupportedProviderException = new UnsupportedProviderException($invalidProvider);
        $expectedException = new UnrecoverableMessageHandlingException(
            $unsupportedProviderException->getMessage(),
            $unsupportedProviderException->getCode(),
            $unsupportedProviderException
        );

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        ObjectReflector::setProperty(
            $machineProvider,
            MachineProvider::class,
            'provider',
            $invalidProvider
        );

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        if ($machineProviderStore instanceof MachineProviderStore) {
            $machineProviderStore->store($machineProvider);
        }

        $message = new CreateMachine('id0', $this->machine->getId());

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(Machine::STATE_CREATE_REQUESTED, $this->machine->getState());
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(\Exception $vendorException, \Exception $expectedException): void
    {
        $dropletApiProxy = self::getContainer()->get(DropletProxy::class);
        if ($dropletApiProxy instanceof DropletProxy) {
            $dropletConfiguration = $this->createDropletConfiguration('test-worker-machine id');

            $dropletApiProxy->withCreateCall(
                'test-worker-machine id',
                $dropletConfiguration->getRegion(),
                $dropletConfiguration->getSize(),
                $dropletConfiguration->getImage(),
                $dropletConfiguration->getBackups(),
                $dropletConfiguration->getIpv6(),
                $dropletConfiguration->getVpcUuid(),
                $dropletConfiguration->getSshKeys(),
                $dropletConfiguration->getUserData(),
                $dropletConfiguration->getMonitoring(),
                $dropletConfiguration->getVolumes(),
                $dropletConfiguration->getTags(),
                $vendorException,
            );
        }

        $message = new CreateMachine('id0', $this->machine->getId());

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame(Machine::STATE_CREATE_REQUESTED, $this->machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_CREATE,
            new VendorRuntimeException('Unauthorized', 401)
        );

        $unknownRemoteMachineException = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            self::MACHINE_ID,
            MachineActionInterface::ACTION_CREATE,
            new ResourceNotFoundException('Not Found', 404),
        );

        $rateLimitReset = 1400000;

        $apiLimitExceededException = new ApiLimitExceededException(
            $rateLimitReset,
            self::MACHINE_ID,
            MachineActionInterface::ACTION_CREATE,
            new \DigitalOceanV2\Exception\ApiLimitExceededException('Too Many Requests', 429)
        );

        return [
            'HTTP 401' => [
                'vendorException' => new VendorRuntimeException('Unauthorized', 401),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $authenticationException->getMessage(),
                    $authenticationException->getCode(),
                    $authenticationException
                ),
            ],
            'HTTP 404' => [
                'vendorException' => new ResourceNotFoundException('Not Found', 404),
                'expectedException' => $unknownRemoteMachineException,
            ],
            'HTTP 429' => [
                'vendorException' => $apiLimitExceededException,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $apiLimitExceededException->getMessage(),
                    $apiLimitExceededException->getCode(),
                    $apiLimitExceededException
                ),
            ],
            'HTTP 503' => [
                'vendorException' => new VendorRuntimeException('Service Unavailable', 503),
                'expectedException' => new HttpException(
                    self::MACHINE_ID,
                    MachineActionInterface::ACTION_CREATE,
                    new VendorRuntimeException('Service Unavailable', 503)
                ),
            ],
        ];
    }

    private function createDropletConfiguration(string $name): Configuration
    {
        $factory = self::getContainer()->get(Factory::class);
        if (false === $factory instanceof Factory) {
            throw new \RuntimeException(Factory::class . ' service not found');
        }

        $configuration = $factory->create();
        $configuration = $configuration->withNames([$name]);

        return $configuration->addTags([$name]);
    }
}
