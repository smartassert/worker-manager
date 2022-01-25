<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher;

use App\Entity\Machine;
use App\Message\MachineRequestInterface;
use App\Services\Entity\Store\MachineStore;
use App\Services\MachineRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\SequentialRequestIdFactory;
use App\Tests\Services\TestMachineRequestFactory;
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
        $machineRequestFactory = new TestMachineRequestFactory(
            new MachineRequestFactory(
                new SequentialRequestIdFactory(),
                10000
            )
        );

        return [
            'create' => [
                'message' => $machineRequestFactory->createCreate(self::MACHINE_ID),
                'expectedDelayStamp' => null,
            ],
            'get' => [
                'message' => $machineRequestFactory->createGet(self::MACHINE_ID),
                'expectedDelayStamp' => null,
            ],
            'check machine is active' => [
                'message' => $machineRequestFactory->createCheckIsActive(self::MACHINE_ID),
                'expectedDelayStamp' => new DelayStamp(10000),
            ],
        ];
    }
}
