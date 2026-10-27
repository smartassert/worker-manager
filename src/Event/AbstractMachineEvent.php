<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractMachineEvent extends Event implements MachineEventInterface
{
    /**
     * @param non-empty-string $machineId
     */
    public function __construct(
        private readonly string $machineId,
    ) {}

    public function getMachineId(): string
    {
        return $this->machineId;
    }
}
