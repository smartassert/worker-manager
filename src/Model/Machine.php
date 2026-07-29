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
     *     has_active_state: bool,
     *     has_ending_state: bool,
     *     meta_state: array{
     *       pending: bool,
     *       ended: bool,
     *       succeeded: bool
     *     },
     *     previous_states: value-of<MachineState>[]
     * }
     */
    public function jsonSerialize(): array
    {
        $state = $this->machine->getState();
        $stateCategory = MachineStateCategory::fromState($state);
        $hasEndState = MachineStateCategory::END === $stateCategory;

        $previousStates = [];
        foreach ($state->getPreviousStates() as $previousState) {
            $previousStates[] = $previousState->value;
        }

        return [
            'id' => $this->machine->getId(),
            'state' => $state,
            'ip_addresses' => $this->machine->getIpAddresses(),
            'state_category' => $stateCategory,
            'action_failure' => $this->actionFailure,
            'has_active_state' => MachineStateCategory::ACTIVE === $stateCategory,
            'has_ending_state' => MachineStateCategory::ENDING === $stateCategory,
            'meta_state' => [
                'pending' => $state->isPending(),
                'ended' => $hasEndState,
                'succeeded' => $hasEndState && false === $state->isFailed(),
            ],
            'previous_states' => $previousStates,
        ];
    }
}
