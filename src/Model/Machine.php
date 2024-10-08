<?php

namespace App\Model;

use App\Entity\ActionFailure;
use App\Entity\Machine as MachineEntity;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;

readonly class Machine implements \JsonSerializable
{
    public function __construct(
        private MachineEntity $machine,
        private ?ActionFailure $actionFailure = null,
    ) {
    }

    /**
     * @return array{
     *    id: non-empty-string,
     *    state: MachineState,
     *    ip_addresses: string[],
     *    state_category: MachineStateCategory,
     *    action_failure: ?ActionFailure
     *  }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->machine->getId(),
            'state' => $this->machine->getState(),
            'ip_addresses' => $this->machine->getIpAddresses(),
            'state_category' => $this->machine->getStateCategory(),
            'action_failure' => $this->actionFailure,
        ];
    }
}
