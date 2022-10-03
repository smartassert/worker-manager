<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Message\GetMachine;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Repository\MachineRepository;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private CheckMachineIsActiveHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineRepository $machineRepository;
    private Machine $machine;

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

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }

        $this->machine = new Machine(self::MACHINE_ID);
        $this->machineRepository->add($this->machine);
    }

    /**
     * @dataProvider invokeMachineIsActiveOrEndedDataProvider
     *
     * @param Machine::STATE_* $state
     */
    public function testInvokeMachineIsActiveOrEnded(string $state): void
    {
        $this->machine->setState($state);
        $this->machineRepository->add($this->machine);

        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory(
                new SequentialRequestIdFactory()
            )
        );

        ($this->handler)($machineRequestFactory->createCheckIsActive(self::MACHINE_ID));

        $this->messengerAsserter->assertQueueIsEmpty();
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

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory),
        );

        $request = $machineRequestFactory->createCheckIsActive(self::MACHINE_ID);

        ($this->handler)($request);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new GetMachine('id1', self::MACHINE_ID),
        );

        $this->messengerAsserter->assertMessageAtPositionEquals(1, $request);

        $requestIdFactory->reset();
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
        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory),
        );

        $message = $machineRequestFactory->createCheckIsActive('invalid machine id');

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
    }
}
