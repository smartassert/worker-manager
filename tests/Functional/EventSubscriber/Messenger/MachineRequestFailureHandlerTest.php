<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber\Messenger;

use App\Entity\CreateFailure;
use App\Entity\Machine;
use App\Enum\MachineState;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\UnsupportedProviderException;
use App\Message\CreateMachine;
use App\Message\DeleteMachine;
use App\Message\FindMachine;
use App\Message\GetMachine;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Repository\CreateFailureRepository;
use App\Repository\MachineRepository;
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
            $entityRemover->removeAllForEntity(CreateFailure::class);
        }

        $this->machineRepository->add(new Machine(self::MACHINE_ID));
    }

    /**
     * @dataProvider handleCreateMachineWorkerMessageFailedEventDataProvider
     */
    public function testHandleCreateMachineWorkerMessageFailedEvent(
        \Throwable $throwable,
        MachineState $expectedMachineState,
        CreateFailure $expectedCreateFailure
    ): void {
        $envelope = new Envelope(
            new CreateMachine('unique id', self::MACHINE_ID)
        );

        $event = new WorkerMessageFailedEvent($envelope, 'receiver name not relevant', $throwable);

        $this->eventDispatcher->dispatch($event);

        $machine = $this->machineRepository->find(self::MACHINE_ID);
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
                'expectedMachineState' => MachineState::CREATE_FAILED,
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
                'expectedMachineState' => MachineState::CREATE_FAILED,
                'expectedCreateFailure' => new CreateFailure(
                    self::MACHINE_ID,
                    CreateFailure::CODE_UNSUPPORTED_PROVIDER,
                    CreateFailure::REASON_UNSUPPORTED_PROVIDER
                ),
            ],
            'unknown exception' => [
                'throwable' => new \Exception('Unknown exception'),
                'expectedMachineState' => MachineState::CREATE_FAILED,
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
}
