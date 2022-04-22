<?php

declare(strict_types=1);

namespace App\Tests\Services\ApplicationClient;

use App\Controller\MachineController;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;

class Client
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $healthCheckUrl,
    ) {
    }

    public function makeMachineCreateRequest(
        ?string $authenticationToken,
        string $machineId,
        string $method = 'POST'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->createMachineRequestUrl($machineId),
            $this->createAuthorizationHeader($authenticationToken)
        );
    }

    public function makeMachineStatusRequest(
        ?string $authenticationToken,
        string $machineId,
        string $method = 'GET'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->createMachineRequestUrl($machineId),
            $this->createAuthorizationHeader($authenticationToken)
        );
    }

    public function makeMachineDeleteRequest(
        ?string $authenticationToken,
        string $machineId,
        string $method = 'DELETE'
    ): ResponseInterface {
        return $this->client->makeRequest(
            $method,
            $this->createMachineRequestUrl($machineId),
            $this->createAuthorizationHeader($authenticationToken)
        );
    }

    public function makeGetHealthCheckRequest(string $method = 'GET'): ResponseInterface
    {
        return $this->client->makeRequest($method, $this->healthCheckUrl);
    }

    private function createMachineRequestUrl(string $machineId): string
    {
        return str_replace(MachineController::PATH_COMPONENT_ID, $machineId, MachineController::PATH_MACHINE);
    }

    /**
     * @return array<string, string>
     */
    private function createAuthorizationHeader(?string $authenticationToken): array
    {
        $headers = [];
        if (is_string($authenticationToken)) {
            $headers = [
                'authorization' => 'Bearer ' . $authenticationToken,
            ];
        }

        return $headers;
    }
}
