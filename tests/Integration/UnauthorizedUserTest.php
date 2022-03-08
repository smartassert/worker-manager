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
    public function testRequestAsUnauthorizedUser(string $method, ?string $token): void
    {
        try {
            $response = $this->makeRequest($method, $this->machineUrl, $token);
            self::fail(ClientException::class . ' not thrown');
        } catch (ClientException $e) {
            $response = $e->getResponse();
        }

        self::assertSame(401, $response->getStatusCode());
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    public function requestAsUnauthorizedUserDataProvider(): array
    {
        return [
            'create, invalid token' => [
                'method' => 'POST',
                'token' => 'invalid-token',
            ],
            'status, invalid token' => [
                'method' => 'GET',
                'token' => 'invalid-token',
            ],
            'delete, invalid token' => [
                'method' => 'DELETE',
                'token' => 'invalid-token',
            ],
            'create, no token' => [
                'method' => 'POST',
                'token' => null,
            ],
            'status, no token' => [
                'method' => 'GET',
                'token' => null,
            ],
            'delete, no token' => [
                'method' => 'DELETE',
                'token' => null,
            ],
        ];
    }
}
