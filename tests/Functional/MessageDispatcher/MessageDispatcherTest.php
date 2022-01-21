<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\GetMachine;
use App\Message\MachineRequestInterface;
use App\Services\Entity\Store\MachineStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use webignition\SymfonyMessengerMessageDispatcher\MessageDispatcher;

class MessageDispatcherTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MACHINE_ID = 'id';

    private MessageDispatcher $dispatcher;
    private MessengerAsserter $messengerAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(MessageDispatcher::class);
        \assert($dispatcher instanceof MessageDispatcher);
        $this->dispatcher = $dispatcher;

        $machineStore = self::getContainer()->get(MachineStore::class);
        \assert($machineStore instanceof MachineStore);
        $machineStore->store(new Machine(md5('id content'), Machine::STATE_UNKNOWN));

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;
    }

    /**
     * @dataProvider dispatchDataProvider
     */
    public function testDispatch(MachineRequestInterface $message, ?StampInterface $expectedDelayStamp): void
    {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->dispatcher->dispatch($message);

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $message);

        if ($expectedDelayStamp instanceof StampInterface) {
            $this->messengerAsserter->assertEnvelopeContainsStamp(
                $this->messengerAsserter->getEnvelopeAtPosition(0),
                $expectedDelayStamp,
                0
            );
        } else {
            $this->messengerAsserter->assertEnvelopeNotContainsStampsOfType(
                $this->messengerAsserter->getEnvelopeAtPosition(0),
                DelayStamp::class
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function dispatchDataProvider(): array
    {
        return [
            'create' => [
                'message' => new CreateMachine('id0', self::MACHINE_ID),
                'expectedDelayStamp' => null,
            ],
            'get' => [
                'message' => new GetMachine('id0', self::MACHINE_ID),
                'expectedDelayStamp' => null,
            ],
            'check machine is active' => [
                'message' => new CheckMachineIsActive('id0', self::MACHINE_ID),
                'expectedDelayStamp' => new DelayStamp(10000),
            ],
        ];
    }
}
