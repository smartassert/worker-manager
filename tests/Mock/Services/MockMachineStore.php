<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\Machine;
use App\Services\Entity\Store\MachineStore;
use Mockery\MockInterface;
use Monolog\Test\TestCase;

class MockMachineStore
{
    private MachineStore $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(MachineStore::class);
    }

    public function getMock(): MachineStore
    {
        return $this->mock;
    }

    public function withFindCall(string $machineId, ?Machine $machine): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('find')
            ->with($machineId)
            ->andReturn($machine)
        ;

        return $this;
    }

    public function withStoreCall(Machine $machine): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('store')
            ->withArgs(function (Machine $passedMachine) use ($machine) {
                TestCase::assertEquals($machine, $passedMachine);

                return true;
            })
        ;

        return $this;
    }
}
