<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Entity\MachineProvider;
use App\Services\MachineManager;
use Mockery\MockInterface;

class MockMachineManager
{
    private MachineManager $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(MachineManager::class);
    }

    public function getMock(): MachineManager
    {
        return $this->mock;
    }

    public function withCreateCallThrowingException(
        MachineProvider $machineProvider,
        \Exception $exception
    ): self {
        if ($this->mock instanceof MockInterface) {
            $this->mock
                ->shouldReceive('create')
                ->with($machineProvider)
                ->andThrow($exception)
            ;
        }

        return $this;
    }
}
