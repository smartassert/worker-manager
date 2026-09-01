<?php

declare(strict_types=1);

namespace App\Tests\Services\ApplicationClient;

use Psr\Http\Message\ResponseInterface;
use SmartAssert\SymfonyTestClient\ClientInterface;

class Client
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $healthCheckUrl,
        private readonly string $statusUrl,
    ) {}

    public function makeMachineCreateRequest(
        ?string $authenticationToken,
        string $machineId,
        ?string $notifyUrl = null,
        string $method = 'POST'
    ): ResponseInterface {
        $header = $this->createAuthorizationHeader($authenticationToken);
        $requestBody = null;

        if (is_string($notifyUrl)) {
            $header['content-type'] = 'application/x-www-form-urlencoded';
            $requestBody = http_build_query(['notify_url' => $notifyUrl]);
        }

        return $this->client->makeRequest(
            $method,
            $this->createMachineRequestUrl($machineId),
            $header,
            $requestBody,
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

    public function makeGetStatusRequest(string $method = 'GET'): ResponseInterface
    {
        return $this->client->makeRequest($method, $this->statusUrl);
    }

    private function createMachineRequestUrl(string $machineId): string
    {
        return str_replace('{id}', $machineId, '/machine/{id}');
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
