<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber\Messenger;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Message\RemoteMachineMessageInterface;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Repository\CreateFailureRepository;
use App\Services\Entity\Store\MachineStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class MachineRequestFailureHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private EventDispatcherInterface $eventDispatcher;
    private MachineStore $machineStore;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
            $entityRemover->removeAllForEntity(CreateFailure::class);
        }

        $machineStore->store(new Machine(self::MACHINE_ID));
    }

    /**
     * @dataProvider handleCreateMachineWorkerMessageFailedEventDataProvider
     */
    public function testHandleCreateMachineWorkerMessageFailedEvent(
        \Throwable $throwable,
        string $expectedMachineState,
        CreateFailure $expectedCreateFailure
    ): void {
        $envelope = new Envelope(
            new CreateMachine('unique id', self::MACHINE_ID)
        );

        $event = new WorkerMessageFailedEvent($envelope, 'receiver name not relevant', $throwable);

        $this->eventDispatcher->dispatch($event);

        $machine = $this->machineStore->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $machine);

        self::assertSame($expectedMachineState, $machine->getState());

        $createFailureRepository = self::getContainer()->get(CreateFailureRepository::class);
        \assert($createFailureRepository instanceof CreateFailureRepository);

        self::assertEquals($expectedCreateFailure, $createFailureRepository->find(self::MACHINE_ID));
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
                    MachineActionInterface::ACTION_GET,
                    new \Exception()
                ),
                'expectedMachineState' => Machine::STATE_CREATE_FAILED,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_API_LIMIT_EXCEEDED,
                    CreateFailure::REASON_API_LIMIT_EXCEEDED,
                    [
                        'reset-timestamp' => 123,
                    ]
                ),
            ],
            'unsupported provider' => [
                'throwable' => new UnsupportedProviderException(ProviderInterface::NAME_DIGITALOCEAN),
                'expectedMachineState' => Machine::STATE_CREATE_FAILED,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNSUPPORTED_PROVIDER,
                    CreateFailure::REASON_UNSUPPORTED_PROVIDER
                ),
            ],
            'unknown exception' => [
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => Machine::STATE_CREATE_FAILED,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNKNOWN,
                    CreateFailure::REASON_UNKNOWN
                ),
            ],
        ];
    }

    /**
     * @dataProvider handleWorkerMessageFailedEventDataProvider
     */
    public function testHandleWorkerMessageFailedEvent(
        RemoteMachineMessageInterface $message,
        string $expectedMachineState,
    ): void {
        $envelope = new Envelope($message);
        $event = new WorkerMessageFailedEvent($envelope, 'receiver name not relevant', new \Exception());

        $this->eventDispatcher->dispatch($event);

        $machine = $this->machineStore->find(self::MACHINE_ID);
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
                'expectedMachineState' => Machine::STATE_DELETE_FAILED,
            ],
            FindMachine::class => [
                'message' => new FindMachine('unique id', self::MACHINE_ID),
                'expectedMachineState' => Machine::STATE_FIND_NOT_FINDABLE,
            ],
            GetMachine::class => [
                'message' => new GetMachine('unique id', self::MACHINE_ID),
                'expectedMachineState' => Machine::STATE_FIND_NOT_FOUND,
            ],
        ];
    }
}
