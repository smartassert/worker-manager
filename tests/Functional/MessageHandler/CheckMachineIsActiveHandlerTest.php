<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Repository\MachineRepository;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private MachineRepository $machineRepository;
    private Machine $machine;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $machineRequestFactory = self::getContainer()->get(TestMachineRequestFactory::class);
        \assert($machineRequestFactory instanceof TestMachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $this->machine = new Machine(self::MACHINE_ID);
        $this->machineRepository->add($this->machine);
    }

    public function testHandlerExistsInContainerAndIsAMessageHandler(): void
    {
        $handler = self::getContainer()->get(CheckMachineIsActiveHandler::class);
        self::assertInstanceOf(CheckMachineIsActiveHandler::class, $handler);
        self::assertInstanceOf(MessageHandlerInterface::class, $handler);
    }

    /**
     * @dataProvider invokeMachineIsActiveOrEndedDataProvider
     */
    public function testInvokeMachineIsActiveOrEnded(MachineState $state): void
    {
        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        $this->machine->setState($state);
        $this->machineRepository->add($this->machine);

        ($handler)($this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID));
    }

    /**
     * @return array<mixed>
     */
    public function invokeMachineIsActiveOrEndedDataProvider(): array
    {
        return [
            MachineState::CREATE_FAILED->value => [
                'state' => MachineState::CREATE_FAILED,
            ],
            MachineState::UP_ACTIVE->value => [
                'state' => MachineState::UP_ACTIVE,
            ],
            MachineState::DELETE_RECEIVED->value => [
                'state' => MachineState::DELETE_RECEIVED,
            ],
            MachineState::DELETE_REQUESTED->value => [
                'state' => MachineState::DELETE_REQUESTED,
            ],
            MachineState::DELETE_FAILED->value => [
                'state' => MachineState::DELETE_FAILED,
            ],
            MachineState::DELETE_DELETED->value => [
                'state' => MachineState::DELETE_DELETED,
            ],
        ];
    }

    /**
     * @dataProvider handleMachineIsPreActiveDataProvider
     */
    public function testHandleMachineIsPreActive(MachineState $state): void
    {
        $this->machine->setState($state);
        $this->machineRepository->add($this->machine);

        $request = $this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID);
        $expectedMachineRequestCollection = array_merge(
            $request->getOnSuccessCollection(),
            [$request],
        );

        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher
            ->shouldReceive('dispatchCollection')
            ->withArgs(function (array $machineRequestCollection) use ($expectedMachineRequestCollection) {
                self::assertEquals($expectedMachineRequestCollection, $machineRequestCollection);

                return true;
            })
        ;

        $handler = $this->createHandler($machineRequestDispatcher);

        ($handler)($request);
    }

    /**
     * @return array<mixed>
     */
    public function handleMachineIsPreActiveDataProvider(): array
    {
        return [
            MachineState::CREATE_RECEIVED->value => [
                'state' => MachineState::CREATE_RECEIVED,
            ],
            MachineState::CREATE_REQUESTED->value => [
                'state' => MachineState::CREATE_REQUESTED,
            ],
            MachineState::UP_STARTED->value => [
                'state' => MachineState::UP_STARTED,
            ],
        ];
    }

    public function testHandleMachineDoesNotExist(): void
    {
        $machineRequestDispatcher = \Mockery::mock(MachineRequestDispatcher::class);
        $machineRequestDispatcher->shouldNotReceive('dispatch');
        $machineRequestDispatcher->shouldNotReceive('dispatchCollection');

        $handler = $this->createHandler($machineRequestDispatcher);

        $message = $this->machineRequestFactory->createCheckIsActive('invalid machine id');

        ($handler)($message);
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): CheckMachineIsActiveHandler
    {
        return new CheckMachineIsActiveHandler($machineRequestDispatcher, $this->machineRepository);
    }
}
