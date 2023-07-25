<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractMachineRequest implements MachineRequestInterface
{
    /**
     * @param non-empty-string $uniqueId
     * @param non-empty-string $machineId
     */
    public function __construct(
        private readonly string $uniqueId,
        private readonly string $machineId,
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
