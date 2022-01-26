<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
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
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\HttpResponseFactory;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Entity\Droplet as DropletEntity;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use webignition\ObjectReflector\ObjectReflector;

class CreateMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'machine id';

    private CreateMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private Machine $machine;
    private MessageStateEntityAsserter $messageStateEntityAsserter;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CreateMachineHandler::class);
        \assert($handler instanceof CreateMachineHandler);
        $this->handler = $handler;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machine = new Machine(self::MACHINE_ID);
        $machineStore->store($this->machine);

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machineProviderStore->store($machineProvider);

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;
    }

    public function testInvokeSuccess(): void
    {
        self::assertSame([], ObjectReflector::getProperty($this->machine, 'ip_addresses'));

        $dropletData = [
            'id' => 123,
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
        $this->mockHandler->append(HttpResponseFactory::fromDropletEntity($expectedDropletEntity));

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory)
        );

        $message = $machineRequestFactory->createCreate(self::MACHINE_ID);

        ($this->handler)($message);

        $requestIdFactory->reset(1);

        $expectedRequest = $machineRequestFactory->createCheckIsActive(self::MACHINE_ID);

        $expectedRemoteMachine = new RemoteMachine($expectedDropletEntity);
        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedRequest);

        self::assertSame($expectedRemoteMachine->getState(), $this->machine->getState());
        self::assertSame(
            $expectedRemoteMachine->getIpAddresses(),
            ObjectReflector::getProperty($this->machine, 'ip_addresses')
        );

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($expectedRequest->getUniqueId()));
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

        $message = new CreateMachine('id0', $machine->getId());
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
        $machine = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_RECEIVED);
        $machineProvider = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        $authenticationException = new AuthenticationException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_CREATE,
            new RuntimeException('Unauthorized', 401)
        );

        $invalidProvider = 'invalid';
        $unsupportedProviderException = new UnsupportedProviderException($invalidProvider);

        $unknownRemoteMachineException = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            self::MACHINE_ID,
            MachineActionInterface::ACTION_CREATE,
            new ResourceNotFoundException('Not Found', 404),
        );

        $rateLimitReset = 1400000;

        $apiLimitExceededException = new ApiLimitExceededException(
            $rateLimitReset,
            $machine->getId(),
            MachineActionInterface::ACTION_CREATE,
            new \DigitalOceanV2\Exception\ApiLimitExceededException('Too Many Requests', 429)
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
                'expectedException' => $unknownRemoteMachineException,
            ],
            'HTTP 429' => [
                'httpFixture' => new Response(
                    429,
                    [
                        'ratelimit-reset' => (string) $rateLimitReset,
                    ]
                ),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $apiLimitExceededException->getMessage(),
                    $apiLimitExceededException->getCode(),
                    $apiLimitExceededException
                ),
            ],
            'HTTP 503' => [
                'httpFixture' => new Response(503),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new HttpException(
                    $machine->getId(),
                    MachineActionInterface::ACTION_CREATE,
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
