<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Machine;
use App\Entity\MessageState;
use App\Message\CheckMachineIsActive;
use App\Message\GetMachine;
use App\MessageHandler\CheckMachineIsActiveHandler;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class CheckMachineIsActiveHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private CheckMachineIsActiveHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private MachineStore $machineStore;
    private MachineRequestFactory $machineRequestFactory;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $handler = self::$container->get(CheckMachineIsActiveHandler::class);
        \assert($handler instanceof CheckMachineIsActiveHandler);
        $this->handler = $handler;

        $machineStore = self::$container->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $this->machineStore = $machineStore;

        $messengerAsserter = self::$container->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $machineRequestFactory = self::$container->get(MachineRequestFactory::class);
        \assert($machineRequestFactory instanceof MachineRequestFactory);
        $this->machineRequestFactory = $machineRequestFactory;

        $messageStateEntityAsserter = self::$container->get(MessageStateEntityAsserter::class);
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
        $machine = new Machine(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machine->setState($state);
        $this->machineStore->store($machine);

        ($this->handler)(new CheckMachineIsActive('id0', self::MACHINE_ID));

        $this->messengerAsserter->assertQueueIsEmpty();
        $this->messageStateEntityAsserter->assertCount(0);
    }

    /**
     * @return array[]
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
        $machine = new Machine(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $machine->setState($state);
        $this->machineStore->store($machine);

        $request = $this->machineRequestFactory->createCheckIsActive(self::MACHINE_ID);

        ($this->handler)($request);

        $this->messengerAsserter->assertMessageAtPositionEquals(
            0,
            new GetMachine('id1', self::MACHINE_ID),
        );

        $this->messengerAsserter->assertMessageAtPositionEquals(1, $request);

        $this->messageStateEntityAsserter->assertCount(2);
        $this->messageStateEntityAsserter->assertHas(new MessageState('id2'));
        $this->messageStateEntityAsserter->assertHas(new MessageState('id3'));
    }

    /**
     * @return array[]
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
