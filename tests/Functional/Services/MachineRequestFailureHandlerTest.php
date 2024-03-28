<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use App\Enum\ActionFailure\Code;
use App\Enum\ActionFailure\Reason;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Model\DigitalOcean\RemoteMachine;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Services\MachineRequestFailureHandler;
use App\Services\MessageHandlerExceptionStackFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use Beste\Psr\Log\Record;
use Beste\Psr\Log\Records;
use Beste\Psr\Log\TestLogger;
use DigitalOceanV2\Exception\RuntimeException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

class MachineRequestFailureHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private EventDispatcherInterface $eventDispatcher;
    private MachineRepository $machineRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(ActionFailure::class);
        }

        $this->machineRepository->add(new Machine(self::MACHINE_ID));
    }

    public function testIsWorkerMessageFailedEventBundleExceptionHandler(): void
    {
        self::assertInstanceOf(
            ExceptionHandlerInterface::class,
            self::getContainer()->get(MachineRequestFailureHandler::class)
        );
    }

    /**
     * @dataProvider handleCreateMachineWorkerMessageFailedEventDataProvider
     */
    public function testHandleCreateMachineWorkerMessageFailedEvent(
        \Throwable $throwable,
        MachineState $expectedMachineState,
        ActionFailure $expectedActionFailure
    ): void {
        $envelope = new Envelope(
            new CreateMachine('unique id', self::MACHINE_ID)
        );

        $event = new WorkerMessageFailedEvent($envelope, 'receiver name not relevant', $throwable);

        $this->eventDispatcher->dispatch($event);

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);

        self::assertSame($expectedMachineState, $machine->getState());

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        self::assertEquals($expectedActionFailure, $actionFailureRepository->find(self::MACHINE_ID));
    }

    /**
     * @return array<mixed>
     */
    public function handleCreateMachineWorkerMessageFailedEventDataProvider(): array
    {
        return [
            'api limit exceeded' => [
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    Code::API_LIMIT_EXCEEDED,
                    Reason::API_LIMIT_EXCEEDED,
                    [
                        'reset-timestamp' => 123,
                    ]
                ),
            ],
            'unsupported provider' => [
                'throwable' => new UnsupportedProviderException(RemoteMachine::TYPE),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    Code::UNSUPPORTED_PROVIDER,
                    Reason::UNSUPPORTED_PROVIDER
                ),
            ],
            'unknown exception' => [
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN
                ),
            ],
        ];
    }

    /**
     * @dataProvider handleWorkerMessageFailedEventDataProvider
     */
    public function testHandleWorkerMessageFailedEvent(object $message, MachineState $expectedMachineState): void
    {
        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'receiver name not relevant', new \Exception());

        $this->eventDispatcher->dispatch($event);

        $machine = $this->machineRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);

        self::assertSame($expectedMachineState, $machine->getState());
    }

    /**
     * @return array<mixed>
     */
    public function handleWorkerMessageFailedEventDataProvider(): array
    {
        return [
            DeleteMachine::class => [
                'message' => new DeleteMachine('unique id', self::MACHINE_ID),
                'expectedMachineState' => MachineState::DELETE_FAILED,
            ],
            FindMachine::class => [
                'message' => new FindMachine('unique id', self::MACHINE_ID),
                'expectedMachineState' => MachineState::FIND_NOT_FINDABLE,
            ],
            GetMachine::class => [
                'message' => new GetMachine('unique id', self::MACHINE_ID),
                'expectedMachineState' => MachineState::FIND_NOT_FOUND,
            ],
        ];
    }

    /**
     * @dataProvider exceptionLoggingDataProvider
     *
     * @param callable(MachineRequestInterface): \Throwable $throwableCreator
     * @param callable(MachineRequestInterface): Records    $expectedCreator
     */
    public function testExceptionLogging(callable $throwableCreator, callable $expectedCreator): void
    {
        $actionFailureFactory = self::getContainer()->get(ActionFailureFactory::class);
        \assert($actionFailureFactory instanceof ActionFailureFactory);

        $exceptionStackFactory = self::getContainer()->get(MessageHandlerExceptionStackFactory::class);
        \assert($exceptionStackFactory instanceof MessageHandlerExceptionStackFactory);

        $messengerAuditLogger = TestLogger::create();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);

        $machineId = (string) new Ulid();
        \assert('' !== $machineId);

        $machine = new Machine($machineId);
        $machineRepository->add($machine);

        $messageId = (string) new Ulid();
        \assert('' !== $messageId);

        $message = new GetMachine($messageId, $machineId);

        $handler = new MachineRequestFailureHandler(
            $actionFailureFactory,
            $exceptionStackFactory,
            $messengerAuditLogger,
            $machineRepository,
        );

        $envelope = new Envelope($message);

        $throwable = $throwableCreator($message);

        $handler->handle($envelope, $throwable);

        $expected = $expectedCreator($message);

        self::assertEquals($expected, $messengerAuditLogger->records);
    }

    /**
     * @return array<mixed>
     */
    public function exceptionLoggingDataProvider(): array
    {
        return [
            'Not UnrecoverableMessageHandlingException, no previous' => [
                'throwableCreator' => function () {
                    return new \Exception('Exception message', 123);
                },
                'expectedCreator' => function (MachineRequestInterface $message) {
                    $records = Records::empty();
                    $records->add(Record::with(
                        'critical',
                        'Exception message',
                        [
                            'message_id' => $message->getUniqueId(),
                            'machine_id' => $message->getMachineId(),
                            'code' => 123,
                            'exception' => \Exception::class,
                        ]
                    ));

                    return $records;
                },
            ],
            'Is UnrecoverableMessageHandlingException' => [
                'throwableCreator' => function () {
                    return new UnrecoverableMessageHandlingException(
                        'bar',
                        456,
                        new \Exception('Exception message', 123)
                    );
                },
                'expectedCreator' => function (MachineRequestInterface $message) {
                    $records = Records::empty();
                    $records->add(Record::with(
                        'critical',
                        'Exception message',
                        [
                            'message_id' => $message->getUniqueId(),
                            'machine_id' => $message->getMachineId(),
                            'code' => 123,
                            'exception' => \Exception::class,
                        ]
                    ));

                    return $records;
                },
            ],
            'Is UnrecoverableMessageHandlingException with previous stack' => [
                'throwableCreator' => function (MachineRequestInterface $message) {
                    return new UnrecoverableMessageHandlingException(
                        'foobar',
                        789,
                        new MachineActionFailedException(
                            $message->getMachineId(),
                            MachineAction::FIND,
                            [
                                new ApiLimitExceededException(
                                    123,
                                    $message->getMachineId(),
                                    MachineAction::GET,
                                    new RuntimeException(
                                        'API limit exceeded',
                                        429
                                    )
                                ),
                            ]
                        ),
                    );
                },
                'expectedCreator' => function (MachineRequestInterface $message) {
                    $records = Records::empty();
                    $records->add(Record::with(
                        'critical',
                        'Action "find" on machine "' . $message->getMachineId() . '" failed',
                        [
                            'message_id' => $message->getUniqueId(),
                            'machine_id' => $message->getMachineId(),
                            'code' => 0,
                            'exception' => MachineActionFailedException::class,
                        ]
                    ));

                    $records->add(Record::with(
                        'critical',
                        sprintf(
                            'ApiLimitExceededException Unable to perform action "get" for resource "%s"',
                            $message->getMachineId()
                        ),
                        [
                            'message_id' => $message->getUniqueId(),
                            'machine_id' => $message->getMachineId(),
                            'code' => 0,
                            'exception' => ApiLimitExceededException::class,
                        ]
                    ));

                    $records->add(Record::with(
                        'critical',
                        'API limit exceeded',
                        [
                            'message_id' => $message->getUniqueId(),
                            'machine_id' => $message->getMachineId(),
                            'code' => 429,
                            'exception' => RuntimeException::class,
                        ]
                    ));

                    return $records;
                },
            ],
        ];
    }
}
