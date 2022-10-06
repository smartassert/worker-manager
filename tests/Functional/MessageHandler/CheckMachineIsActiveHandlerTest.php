<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Repository\MachineRepository;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestMachineRequestFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private CheckMachineIsActiveHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineRepository $machineRepository;
    private Machine $machine;
    private TestMachineRequestFactory $machineRequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CheckMachineIsActiveHandler::class);
        \assert($handler instanceof CheckMachineIsActiveHandler);
        $this->handler = $handler;

        $machineRepository = self::getContainer()->get(MachineRepository::class);
        \assert($machineRepository instanceof MachineRepository);
        $this->machineRepository = $machineRepository;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

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
     *
     * @param Machine::STATE_* $state
     */
    public function testInvokeMachineIsActiveOrEnded(string $state): void
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
            Machine::STATE_CREATE_FAILED => [
                'state' => Machine::STATE_CREATE_FAILED,
            ],
            Machine::STATE_UP_ACTIVE => [
                'state' => Machine::STATE_UP_ACTIVE,
            ],
            Machine::STATE_DELETE_RECEIVED => [
                'state' => Machine::STATE_DELETE_RECEIVED,
            ],
            Machine::STATE_DELETE_REQUESTED => [
                'state' => Machine::STATE_DELETE_REQUESTED,
            ],
            Machine::STATE_DELETE_FAILED => [
                'state' => Machine::STATE_DELETE_FAILED,
            ],
            Machine::STATE_DELETE_DELETED => [
                'state' => Machine::STATE_DELETE_DELETED,
            ],
        ];
    }

    /**
     * @dataProvider handleMachineIsPreActiveDataProvider
     *
     * @param Machine::STATE_* $state
     */
    public function testHandleMachineIsPreActive(string $state): void
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
            Machine::STATE_CREATE_RECEIVED => [
                'state' => Machine::STATE_CREATE_RECEIVED,
            ],
            Machine::STATE_CREATE_REQUESTED => [
                'state' => Machine::STATE_CREATE_REQUESTED,
            ],
            Machine::STATE_UP_STARTED => [
                'state' => Machine::STATE_UP_STARTED,
            ],
        ];
    }

    public function testHandleMachineDoesNotExist(): void
    {
        $message = $this->machineRequestFactory->createCheckIsActive('invalid machine id');

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    private function createHandler(MachineRequestDispatcher $machineRequestDispatcher): CheckMachineIsActiveHandler
    {
        return new CheckMachineIsActiveHandler($machineRequestDispatcher, $this->machineRepository);
    }
}
