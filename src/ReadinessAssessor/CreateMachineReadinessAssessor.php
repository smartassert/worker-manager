<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;

readonly class CreateMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(string $machineId): MessageHandlingReadiness
    {
        $machine = $this->machineRepository->find($machineId);
        if (null === $machine) {
            return MessageHandlingReadiness::NEVER;
        }

        $state = $machine->getState();
        $stateCategory = MachineStateCategory::fromState($state);
        if (MachineStateCategory::END === $stateCategory) {
            return MessageHandlingReadiness::NEVER;
        }

        if (MachineState::CREATE_RECEIVED !== $state && MachineState::CREATE_REQUESTED !== $state) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
