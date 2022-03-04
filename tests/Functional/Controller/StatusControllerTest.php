<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\StatusController;
use App\Tests\AbstractBaseFunctionalTest;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatusControllerTest extends AbstractBaseFunctionalTest
{
    /**
     * @dataProvider getDataProvider
     *
     * @param array{"version": string} $expectedResponseData
     */
    public function testGet(array $expectedResponseData): void
    {
        $this->client->request('GET', StatusController::ROUTE);

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(JsonResponse::class, $response);

        $versionParameter = self::getContainer()->getParameter('version');
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
            'default' => [
                'expectedResponseData' => [
                    'version' => '{{ version }}',
                    'ready' => true,
                ],
            ],
        ];
    }
}
