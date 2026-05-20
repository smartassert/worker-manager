<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\ActionFailure;
use App\Entity\Machine as MachineEntity;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;

readonly class Machine implements \JsonSerializable
{
    public function __construct(
        private MachineEntity $machine,
        private ?ActionFailure $actionFailure = null
    ) {}

    /**
     * @return array{
     *     id: non-empty-string,
     *     state: MachineState,
     *     ip_addresses: string[],
     *     state_category: MachineStateCategory,
     *     action_failure: ?ActionFailure,
     *     has_failed_state: bool,
     *     has_active_state: bool,
     *     has_ending_state: bool,
     *     has_end_state: bool,
     *     meta_state: array{
     *       pending: bool,
     *       ended: bool,
     *       succeeded: bool
     *     }
     * }
     */
    public function jsonSerialize(): array
    {
        $stateCategory = $this->machine->getStateCategory();

        $state = $this->machine->getState();

        $hasFailedState = MachineState::isFailed($state);
        $hasEndState = MachineStateCategory::END === $stateCategory;

        return [
            'id' => $this->machine->getId(),
            'state' => $state,
            'ip_addresses' => $this->machine->getIpAddresses(),
            'state_category' => $stateCategory,
            'action_failure' => $this->actionFailure,
            'has_failed_state' => $hasFailedState,
            'has_active_state' => MachineStateCategory::ACTIVE === $stateCategory,
            'has_ending_state' => MachineStateCategory::ENDING === $stateCategory,
            'has_end_state' => $hasEndState,
            'meta_state' => [
                'pending' => MachineState::isPending($state),
                'ended' => $hasEndState,
                'succeeded' => $hasEndState && false === $hasFailedState,
            ],
        ];
    }
}
