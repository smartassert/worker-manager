<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\MessageQueueSizeController;
use App\Entity\MessageState;
use App\Services\Entity\Store\MessageStateStore;
use App\Tests\AbstractBaseFunctionalTest;

class MessageQueueSizeControllerTest extends AbstractBaseFunctionalTest
{
    private MessageStateStore $messageStateStore;

    protected function setUp(): void
    {
        parent::setUp();

        $messageStateStore = self::$container->get(MessageStateStore::class);
        \assert($messageStateStore instanceof MessageStateStore);
        $this->messageStateStore = $messageStateStore;
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param MessageState[] $messageStateEntities     */
    public function testGet(array $messageStateEntities, int $expectedMessageQueueSize): void
    {
        foreach ($messageStateEntities as $messageStateEntity) {
            $this->messageStateStore->store($messageStateEntity);
        }

        $this->client->request('GET', MessageQueueSizeController::ROUTE);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            (string) $expectedMessageQueueSize,
            $response->getContent()
        );
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'none' => [
                'messageStateEntities' => [],
                'expectedMessageQueueSize' => 0,
            ],
            'one' => [
                'messageStateEntities' => [
                    new MessageState('id0'),
                ],
                'expectedMessageQueueSize' => 1,
            ],
            'many' => [
                'messageStateEntities' => [
                    new MessageState('id0'),
                    new MessageState('id1'),
                    new MessageState('id2'),
                ],
                'expectedMessageQueueSize' => 3,
            ],
        ];
    }
}
