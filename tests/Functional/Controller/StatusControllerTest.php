<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\StatusController;
use App\Entity\MessageState;
use App\Services\Entity\Store\MessageStateStore;
use App\Tests\AbstractBaseFunctionalTest;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatusControllerTest extends AbstractBaseFunctionalTest
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
     * @param MessageState[]                            $messageStateEntities
     * @param array{"version": string, "idle": boolean} $expectedResponseData
     */
    public function testGet(array $messageStateEntities, array $expectedResponseData): void
    {
        foreach ($messageStateEntities as $messageStateEntity) {
            $this->messageStateStore->store($messageStateEntity);
        }

        $this->client->request('GET', StatusController::ROUTE);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(JsonResponse::class, $response);

        $versionParameter = self::$container->getParameter('version');
        $versionParameter = is_string($versionParameter) ? $versionParameter : 'unknown';

        $expectedResponseData['version'] = str_replace(
            '{{ version }}',
            $versionParameter,
            $expectedResponseData['version']
        );

        self::assertSame(
            $expectedResponseData,
            json_decode((string) $response->getContent(), true)
        );
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no message state entities' => [
                'messageStateEntities' => [],
                'expectedResponseData' => [
                    'version' => '{{ version }}',
                    'idle' => true,
                ],
            ],
            'one message state entity' => [
                'messageStateEntities' => [
                    new MessageState('id0'),
                ],
                'expectedResponseData' => [
                    'version' => '{{ version }}',
                    'idle' => false,
                ],
            ],
            'many message state entities' => [
                'messageStateEntities' => [
                    new MessageState('id0'),
                    new MessageState('id1'),
                    new MessageState('id2'),
                ],
                'expectedResponseData' => [
                    'version' => '{{ version }}',
                    'idle' => false,
                ],
            ],
        ];
    }
}
