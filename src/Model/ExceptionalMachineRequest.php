<?php

namespace App\Model;

use App\Entity\Machine;
use App\Message\MachineRequestInterface;

/**
 * @phpstan-type SerializedExceptionalMachineRequest array{
 *   message_id: non-empty-string,
 *   machine_id: non-empty-string,
 *   code: int,
 *   exception: class-string
 * }
 */
readonly class ExceptionalMachineRequest
{
    public function __construct(
        private MachineRequestInterface $machineRequest,
        private \Throwable $throwable
    ) {
    }

    /**
     * @return SerializedExceptionalMachineRequest
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->machineRequest->getUniqueId(),
            'machine_id' => $this->machineRequest->getMachineId(),
            'code' => $this->throwable->getCode(),
            'exception' => $this->throwable::class,
        ];
    }
}
