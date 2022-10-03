<?php

declare(strict_types=1);

namespace App\Tests\Mock\Repository;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use Mockery\MockInterface;

class MockMachineRepository
{
    private MachineRepository $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(MachineRepository::class);
    }

    public function getMock(): MachineRepository
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
}
