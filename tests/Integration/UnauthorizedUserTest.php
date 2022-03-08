<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\MachineController;
use GuzzleHttp\Exception\ClientException;

class UnauthorizedUserTest extends AbstractIntegrationTest
{
    private string $machineUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $machineId = md5((string) rand());
        $this->machineUrl = str_replace('{id}', $machineId, MachineController::PATH_MACHINE);
    }

    /**
     * @dataProvider requestAsUnauthorizedUserDataProvider
     */
    public function testRequestAsUnauthorizedUser(string $method): void
    {
        try {
            $response = $this->httpClient->request($method, $this->machineUrl);
            self::fail(ClientException::class . ' not thrown');
        } catch (ClientException $e) {
            $response = $e->getResponse();
        }

        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function requestAsUnauthorizedUserDataProvider(): array
    {
        return [
            'create' => [
                'method' => 'POST',
            ],
            'status' => [
                'method' => 'GET',
            ],
            'delete' => [
                'method' => 'DELETE',
            ],
        ];
    }
}
