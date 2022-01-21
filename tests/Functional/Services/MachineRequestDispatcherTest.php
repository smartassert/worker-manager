<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\MessageState;
use App\Message\CheckMachineIsActive;
use App\Message\CreateMachine;
use App\Message\MachineRequestInterface;
use App\Model\MachineActionInterface;
use App\Services\MachineRequestDispatcher;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use App\Tests\Services\Asserter\MessengerAsserter;

class MachineRequestDispatcherTest extends AbstractBaseFunctionalTest
{
    private const MACHINE_ID = 'machine id';

    private MachineRequestDispatcher $dispatcher;
    private MessengerAsserter $messengerAsserter;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $dispatcher = self::getContainer()->get(MachineRequestDispatcher::class);
        \assert($dispatcher instanceof MachineRequestDispatcher);
        $this->dispatcher = $dispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $messageStateEntityAsserter = self::getContainer()->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    /**
     * @dataProvider reDispatchDataProvider
     */
    public function testRedispatch(
        MachineRequestInterface $request,
        MachineRequestInterface $expectedDispatchedRequest
    ): void {
        $this->messengerAsserter->assertQueueIsEmpty();

        $this->dispatcher->reDispatch($request);

        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedDispatchedRequest);
        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($request->getUniqueId()));
    }

    /**
     * @return array<mixed>
     */
    public function reDispatchDataProvider(): array
    {
        return [
            MachineActionInterface::ACTION_CREATE => [
                'request' => new CreateMachine('id0', self::MACHINE_ID),
                'expectedDispatchedRequest' => (new CreateMachine('id0', self::MACHINE_ID))->incrementRetryCount(),
            ],
            MachineActionInterface::ACTION_CHECK_IS_ACTIVE => [
                'request' => new CheckMachineIsActive('id0', self::MACHINE_ID),
                'expectedDispatchedRequest' => new CheckMachineIsActive('id0', self::MACHINE_ID),
            ],
        ];
    }
}
