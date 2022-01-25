<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MessageState;
use App\Message\CheckMachineIsActive;
use App\Message\GetMachine;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private CheckMachineIsActiveHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineStore $machineStore;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::getContainer()->get(CheckMachineIsActiveHandler::class);
        \assert($handler instanceof CheckMachineIsActiveHandler);
        $this->handler = $handler;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    /**
     * @dataProvider invokeMachineIsActiveOrEndedDataProvider
     *
     * @param Machine::STATE_* $state
     */
    public function testInvokeMachineIsActiveOrEnded(string $state): void
    {
        $machine = new Machine(self::MACHINE_ID, $state);
        $this->machineStore->store($machine);

        ($this->handler)(new CheckMachineIsActive('id0', self::MACHINE_ID));

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
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
        $machine = new Machine(self::MACHINE_ID, $state);
        $this->machineStore->store($machine);

        $requestIdFactory = new SequentialRequestIdFactory();
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory($requestIdFactory)
        );

        $request = $machineRequestFactory->createCheckIsActive(self::MACHINE_ID);

        ($this->handler)($request);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new GetMachine('id1', self::MACHINE_ID),
        );

        $this->messengerAsserter->assertMessageAtPositionEquals(1, $request);

        $requestIdFactory->reset();

        $expectedMessageStates = [
            new MessageState($requestIdFactory->create()),
            new MessageState($requestIdFactory->create()),
        ];

        $this->messageStateEntityAsserter->assertCount(count($expectedMessageStates));
        foreach ($expectedMessageStates as $expectedMessageState) {
            $this->messageStateEntityAsserter->assertHas($expectedMessageState);
        }
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
        $message = new CheckMachineIsActive('id0', 'invalid machine id');

        ($this->handler)($message);

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }
}
