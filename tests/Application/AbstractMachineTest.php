<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Services\Asserter\MachineResponseAsserter;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractMachineTest extends AbstractApplicationTest
{
    protected MachineResponseAsserter $machineResponseAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $machineResponseAsserter = self::getContainer()->get(MachineResponseAsserter::class);
        \assert($machineResponseAsserter instanceof MachineResponseAsserter);
        $this->machineResponseAsserter = $machineResponseAsserter;
    }

    protected function makeValidCreateRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineCreateRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $machineId
        );
    }

    protected function makeValidStatusRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineStatusRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $machineId
        );
    }

    protected function makeValidDeleteRequest(string $machineId): ResponseInterface
    {
        return $this->getApplicationClient()->makeMachineDeleteRequest(
            self::$authenticationConfiguration->getValidApiToken(),
            $machineId
        );
    }
}
