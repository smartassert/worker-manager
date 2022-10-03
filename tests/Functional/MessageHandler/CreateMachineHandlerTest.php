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
use App\Repository\MachineProviderRepository;
use App\Repository\MachineRepository;
use App\Services\MachineNameFactory;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException as VendorRuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private Machine $machine;
    private DropletApiProxy $dropletApiProxy;
    private string $machineName;
    private MachineProviderRepository $machineProviderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $dropletApiProxy = self::getContainer()->get(DropletApiProxy::class);
        \assert($dropletApiProxy instanceof DropletApiProxy);
        $this->dropletApiProxy = $dropletApiProxy;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }

        $machineProviderRepository = self::getContainer()->get(MachineProviderRepository::class);
        \assert($machineProviderRepository instanceof MachineProviderRepository);
        $this->machineProviderRepository = $machineProviderRepository;
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderRepository->add($machineProvider);

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $machineRepository->add($this->machine);

        $machineNameFactory = self::getContainer()->get(MachineNameFactory::class);
        \assert($machineNameFactory instanceof MachineNameFactory);
        $this->machineName = $machineNameFactory->create(self::MACHINE_ID);
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
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $expectedDropletEntity);

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

        $machineProvider = $this->machineProviderRepository->find(self::MACHINE_ID);
        if ($machineProvider instanceof MachineProvider) {
            ObjectReflector::setProperty(
                $machineProvider,
                MachineProvider::class,
                'provider',
                $invalidProvider
            );

            $this->machineProviderRepository->add($machineProvider);
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
        $this->dropletApiProxy->prepareCreateCall($this->machineName, $vendorException);

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
}
