<?php

declare(strict_types=1);

namespace App\Tests\Application;

use Psr\Http\Message\ResponseInterface;
use SmartAssert\TestAuthenticationProviderBundle\ApiTokenProvider;

abstract class AbstractMachineTestCase extends AbstractApplicationTestCase
{
    protected ApiTokenProvider $apiTokenProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $apiTokenProvider = self::getContainer()->get(ApiTokenProvider::class);
        \assert($apiTokenProvider instanceof ApiTokenProvider);
        $this->apiTokenProvider = $apiTokenProvider;
    }

    protected function makeValidCreateRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineCreateRequest(
            $this->apiTokenProvider->get('user@example.com'),
            $machineId
        );
    }

    protected function makeValidStatusRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineStatusRequest(
            $this->apiTokenProvider->get('user@example.com'),
            $machineId
        );
    }

    protected function makeValidDeleteRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineDeleteRequest(
            $this->apiTokenProvider->get('user@example.com'),
            $machineId
        );
    }
}
