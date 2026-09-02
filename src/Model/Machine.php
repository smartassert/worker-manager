<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\ActionFailure;
use App\Entity\Machine as MachineEntity;
use App\Enum\MachineStateCategory;

readonly class Machine implements SerializableMachineInterface
{
    public function __construct(
        private MachineEntity $machine,
        private ?ActionFailure $actionFailure = null
    ) {}

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
            'state' => $state->value,
            'ip_addresses' => $this->machine->getIpAddresses(),
            'state_category' => $stateCategory->value,
            'action_failure' => $this->actionFailure?->toArray(),
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

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
