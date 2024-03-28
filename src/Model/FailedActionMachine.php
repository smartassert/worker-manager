<?php

namespace App\Model;

use App\Entity\ActionFailure;
use App\Entity\Machine;

readonly class FailedActionMachine implements \JsonSerializable
{
    public function __construct(
        private Machine $machine,
        private ActionFailure $actionFailure,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            $this->machine->jsonSerialize(),
            ['action_failure' => $this->actionFailure],
        );
    }
}
