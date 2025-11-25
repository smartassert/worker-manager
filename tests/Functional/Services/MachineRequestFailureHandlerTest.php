<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Exception\MachineActionFailedException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\Stack;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Repository\ActionFailureRepository;
use App\Repository\MachineRepository;
use App\Services\Entity\Factory\ActionFailureFactory;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineRequestFailureHandler;
use App\Services\MessageHandlerExceptionStackFactory;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use Beste\Psr\Log\Record;
use Beste\Psr\Log\Records;
use Beste\Psr\Log\TestLogger;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

class MachineRequestFailureHandlerTest extends AbstractBaseFunctionalTestCase
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

        $machine = new Machine(self::MACHINE_ID);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machine->setProvider(MachineProvider::DIGITALOCEAN);
        $this->machineRepository->add($machine);
    }

    public function testIsWorkerMessageFailedEventBundleExceptionHandler(): void
    {
        self::assertInstanceOf(
            ExceptionHandlerInterface::class,
            self::getContainer()->get(MachineRequestFailureHandler::class)
        );
    }

    #[DataProvider('handleWorkerMessageFailedEventDataProvider')]
    public function testHandleWorkerMessageFailedEvent(
        MachineRequestInterface $message,
        \Throwable $throwable,
        MachineState $expectedMachineState,
        ?ActionFailure $expectedActionFailure
    ): void {
        $envelope = new Envelope($message);

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
    public static function handleWorkerMessageFailedEventDataProvider(): array
    {
        return [
            'create, api limit exceeded' => [
                'message' => new CreateMachine('unique id', self::MACHINE_ID),
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::VENDOR_REQUEST_LIMIT_EXCEEDED,
                    MachineAction::CREATE,
                    [
                        'reset-timestamp' => 123,
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'create, unsupported provider' => [
                'message' => new CreateMachine('unique id', self::MACHINE_ID),
                'throwable' => new UnsupportedProviderException(null),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNSUPPORTED_PROVIDER,
                    MachineAction::CREATE,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'create, unknown exception' => [
                'message' => new CreateMachine('unique id', self::MACHINE_ID),
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::CREATE,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'find, api limit exceeded' => [
                'message' => new FindMachine('unique id', self::MACHINE_ID),
                'throwable' => new ApiLimitExceededException(
                    123,
                    self::MACHINE_ID,
                    MachineAction::GET,
                    new \Exception()
                ),
                'expectedMachineState' => MachineState::FIND_NOT_FINDABLE,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::VENDOR_REQUEST_LIMIT_EXCEEDED,
                    MachineAction::FIND,
                    [
                        'reset-timestamp' => 123,
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'find, unsupported provider' => [
                'message' => new FindMachine('unique id', self::MACHINE_ID),
                'throwable' => new UnsupportedProviderException(null),
                'expectedMachineState' => MachineState::FIND_NOT_FINDABLE,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNSUPPORTED_PROVIDER,
                    MachineAction::FIND,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'find, unknown exception' => [
                'message' => new FindMachine('unique id', self::MACHINE_ID),
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::FIND_NOT_FINDABLE,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::FIND,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'delete, unknown exception' => [
                'message' => new DeleteMachine('unique id', self::MACHINE_ID),
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::DELETE_FAILED,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::DELETE,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
            'get, unknown exception' => [
                'message' => new GetMachine('unique id', self::MACHINE_ID),
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::FIND_NOT_FOUND,
                'expectedActionFailure' => new ActionFailure(
                    self::MACHINE_ID,
                    ActionFailureType::UNKNOWN,
                    MachineAction::GET,
                    [
                        'provider' => MachineProvider::DIGITALOCEAN->value,
                    ]
                ),
            ],
        ];
    }

    /**
     * @param callable(MachineRequestInterface): \Throwable $throwableCreator
     * @param callable(MachineRequestInterface): Records    $expectedCreator
     */
    #[DataProvider('exceptionLoggingDataProvider')]
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

        $machine = new Machine($machineId);
        $machine->setState(MachineState::CREATE_RECEIVED);
        $machineRepository->add($machine);

        $messageId = (string) new Ulid();

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
    public static function exceptionLoggingDataProvider(): array
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
                            new Stack([
                                new ApiLimitExceededException(
                                    123,
                                    $message->getMachineId(),
                                    MachineAction::GET,
                                    new DOApiLimitExceededException(
                                        'API limit exceeded',
                                        123,
                                        0,
                                        5000
                                    ),
                                ),
                            ])
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
                            'exception' => DOApiLimitExceededException::class,
                        ]
                    ));

                    return $records;
                },
            ],
        ];
    }
}
