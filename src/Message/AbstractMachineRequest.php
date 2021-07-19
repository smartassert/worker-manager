<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractMachineRequest implements MachineRequestInterface
{
    public function __construct(
        private string $uniqueId,
        private string $machineId,
    ) {
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function getMachineId(): string
    {
        return $this->machineId;
    }
}
