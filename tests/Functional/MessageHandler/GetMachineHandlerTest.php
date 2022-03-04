<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\UnsupportedProviderException;
use App\Message\GetMachine;
use App\MessageHandler\GetMachineHandler;
use App\Model\DigitalOcean\RemoteMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class GetMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';
    private const REMOTE_ID = 123;

    private GetMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(GetMachineHandler::class);
        \assert($handler instanceof GetMachineHandler);
        $this->handler = $handler;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     */
    public function testInvokeSuccess(
        ResponseInterface $apiResponse,
        Machine $machine,
        Machine $expectedMachine,
    ): void {
        $this->mockHandler->append($apiResponse);

        $this->machineStore->store($machine);

        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->machineProviderStore->store($machineProvider);

        $expectedMachineProvider = clone $machineProvider;

        $message = new GetMachine('id0', $machine->getId());
        ($this->handler)($message);

        self::assertEquals($expectedMachine, $machine);
        self::assertEquals($expectedMachineProvider, $machineProvider);

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    /**
     * @return array<mixed>
     */
    public function invokeSuccessDataProvider(): array
    {
        $ipAddresses = [
            '10.0.0.1',
            '127.0.0.1',
        ];

        $createdDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_NEW,
        ]);

        $upNewDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_NEW,
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ]);

        $upActiveDropletEntity = new DropletEntity([
            'id' => self::REMOTE_ID,
            'status' => RemoteMachine::STATE_ACTIVE,
            'networks' => (object) [
                'v4' => [
                    (object) [
                        'ip_address' => $ipAddresses[0],
                        'type' => 'public',
                    ],
                    (object) [
                        'ip_address' => $ipAddresses[1],
                        'type' => 'public',
                    ],
                ],
            ],
        ]);

        return [
            'updated within initial remote id and initial remote state' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$createdDropletEntity]),
                'machine' => new Machine(self::MACHINE_ID),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED
                ),
            ],
            'updated within initial ip addresses' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$upNewDropletEntity]),
                'machine' => new Machine(self::MACHINE_ID, Machine::STATE_UP_STARTED),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
                    $ipAddresses
                ),
            ],
            'updated within active remote state' => [
                'apiResponse' => HttpResponseFactory::fromDropletEntityCollection([$upActiveDropletEntity]),
                'machine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_STARTED,
                    $ipAddresses
                ),
                'expectedMachine' => new Machine(
                    self::MACHINE_ID,
                    Machine::STATE_UP_ACTIVE,
                    $ipAddresses
                ),
            ],
        ];
    }

    /**
     * @dataProvider invokeThrowsExceptionDataProvider
     */
    public function testInvokeThrowsException(
        ?ResponseInterface $httpFixture,
        Machine $machine,
        MachineProvider $machineProvider,
        \Exception $expectedException
    ): void {
        if ($httpFixture instanceof ResponseInterface) {
            $this->mockHandler->append($httpFixture);
        }

        $this->machineStore->store($machine);
        $this->machineProviderStore->store($machineProvider);

        $message = new GetMachine('id0', $machine->getId());
        $machineState = $machine->getState();

        try {
            ($this->handler)($message);
            $this->fail($expectedException::class . ' not thrown');
        } catch (\Exception $exception) {
            self::assertEquals($expectedException, $exception);
        }

        self::assertSame($machineState, $machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeThrowsExceptionDataProvider(): array
    {
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_FIND_RECEIVED);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            new RuntimeException('Unauthorized', 401)
        );

        $invalidProvider = 'invalid';
        $unsupportedProviderException = new UnsupportedProviderException($invalidProvider);

        $unknownRemoteMachineException = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            self::MACHINE_ID,
            MachineActionInterface::ACTION_GET,
            new ResourceNotFoundException('Not Found', 404),
        );

        return [
            'HTTP 401' => [
                'httpFixture' => new Response(401),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $authenticationException->getMessage(),
                    $authenticationException->getCode(),
                    $authenticationException
                ),
            ],
            'HTTP 404' => [
                'httpFixture' => new Response(404),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $unknownRemoteMachineException->getMessage(),
                    $unknownRemoteMachineException->getCode(),
                    $unknownRemoteMachineException
                ),
            ],
            'HTTP 503' => [
                'httpFixture' => new Response(503),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new HttpException(
                    $machine->getId(),
                    MachineActionInterface::ACTION_GET,
                    new RuntimeException('Service Unavailable', 503)
                ),
            ],
            'Unsupported provider' => [
                'httpFixture' => null,
                'machine' => $machine,
                'machineProvider' => (function () {
                    $invalidProvider = 'invalid';

                    $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
                    ObjectReflector::setProperty(
                        $machineProvider,
                        MachineProvider::class,
                        'provider',
                        $invalidProvider
                    );

                    return $machineProvider;
                })(),
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $unsupportedProviderException->getMessage(),
                    $unsupportedProviderException->getCode(),
                    $unsupportedProviderException
                ),
            ],
        ];
    }
}
