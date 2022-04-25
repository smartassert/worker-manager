<?php

declare(strict_types=1);

namespace App\Tests\Application;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractMachineTest extends AbstractApplicationTest
{
    protected function makeValidCreateRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineCreateRequest(
            $this->authenticationConfiguration->validToken,
            $machineId
        );
    }

    protected function makeValidStatusRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineStatusRequest(
            $this->authenticationConfiguration->validToken,
            $machineId
        );
    }

    protected function makeValidDeleteRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineDeleteRequest(
            $this->authenticationConfiguration->validToken,
            $machineId
        );
    }
}
