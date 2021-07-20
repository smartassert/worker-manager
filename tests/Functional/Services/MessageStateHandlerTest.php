<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\MessageState;
use App\Message\CreateMachine;
use App\Services\MessageStateHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\Asserter\MessageStateEntityAsserter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Uid\Ulid;

class MessageStateHandlerTest extends AbstractBaseFunctionalTest
{
    private MessageStateHandler $messageStateHandler;
    private MessageStateEntityAsserter $messageStateEntityAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $messageStateHandler = self::$container->get(MessageStateHandler::class);
        \assert($messageStateHandler instanceof MessageStateHandler);
        $this->messageStateHandler = $messageStateHandler;

        $messageStateEntityAsserter = self::$container->get(MessageStateEntityAsserter::class);
        \assert($messageStateEntityAsserter instanceof MessageStateEntityAsserter);
        $this->messageStateEntityAsserter = $messageStateEntityAsserter;
    }

    public function testCreate(): void
    {
        $this->messageStateEntityAsserter->assertCount(0);

        $messageId = (string) new Ulid();
        $message = new CreateMachine($messageId, 'machine-id');
        $envelope = new Envelope($message);

        $event = new SendMessageToTransportsEvent($envelope);

        $this->messageStateHandler->create($event);

        $this->messageStateEntityAsserter->assertCount(1);
        $this->messageStateEntityAsserter->assertHas(new MessageState($messageId));
    }
}
