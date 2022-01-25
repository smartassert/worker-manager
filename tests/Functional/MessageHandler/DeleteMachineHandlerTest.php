<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MachineProvider;
use App\Entity\MessageState;
use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\MessageHandler\DeleteMachineHandler;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use DigitalOceanV2\Exception\RuntimeException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class DeleteMachineHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private DeleteMachineHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MockHandler $mockHandler;
    private Machine $machine;
    private MessageStateEntityAsserter $messageStateEntityAsserter;
    private MachineStore $machineStore;
    private MachineProviderStore $machineProviderStore;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(DeleteMachineHandler::class);
        \assert($handler instanceof DeleteMachineHandler);
        $this->handler = $handler;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $this->machine = new Machine(self::MACHINE_ID);
        $this->machine->setState(Machine::STATE_DELETE_RECEIVED);
        $machineStore->store($this->machine);

        $machineProviderStore = self::getContainer()->get(MachineProviderStore::class);
        \assert($machineProviderStore instanceof MachineProviderStore);
        $this->machineProviderStore = $machineProviderStore;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $mockHandler = self::getContainer()->get(MockHandler::class);
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    public function testInvokeSuccess(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        self::assertSame(Machine::STATE_DELETE_RECEIVED, $this->machine->getState());

        $this->mockHandler->append(new Response(204));

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory(
                $requestIdFactory
            )
        );

        $message = $machineRequestFactory->createDelete(self::MACHINE_ID);

        ($this->handler)($message);

        $requestIdFactory->reset();

        self::assertSame(Machine::STATE_DELETE_REQUESTED, $this->machine->getState());

        $expectedMessage = $machineRequestFactory
            ->createFind(self::MACHINE_ID)
            ->withOnNotFoundState(Machine::STATE_DELETE_DELETED)
            ->withReDispatchOnSuccess(true)
        ;

        self::assertInstanceOf(FindMachine::class, $expectedMessage);

        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedMessage);

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($expectedMessage->getUniqueId()));
    }

    public function testInvokeMachineEntityMissing(): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();
        $machineId = 'invalid machine id';

        ($this->handler)(new DeleteMachine('id0', $machineId));

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
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

        $message = new DeleteMachine('id0', $machine->getId());
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
            MachineActionInterface::ACTION_DELETE,
            new RuntimeException('Unauthorized', 401)
        );

        $serviceUnavailableException = new HttpException(
            self::MACHINE_ID,
            MachineActionInterface::ACTION_DELETE,
            new RuntimeException('Service Unavailable', 503)
        );

        $machineNotRemovableAuthenticationException = new MachineNotRemovableException(
            self::MACHINE_ID,
            [
                $authenticationException,
            ]
        );

        $machineNotRemovableServiceUnavailableException = new MachineNotRemovableException(
            self::MACHINE_ID,
            [
                $serviceUnavailableException,
            ]
        );

        return [
            'HTTP 401' => [
                'httpFixture' => new Response(401),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotRemovableAuthenticationException->getMessage(),
                    $machineNotRemovableAuthenticationException->getCode(),
                    $machineNotRemovableAuthenticationException
                ),
            ],
            'HTTP 503' => [
                'httpFixture' => new Response(503),
                'machine' => $machine,
                'machineProvider' => $machineProvider,
                'expectedException' => new UnrecoverableMessageHandlingException(
                    $machineNotRemovableServiceUnavailableException->getMessage(),
                    $machineNotRemovableServiceUnavailableException->getCode(),
                    $machineNotRemovableServiceUnavailableException
                ),
            ],
        ];
    }
}
