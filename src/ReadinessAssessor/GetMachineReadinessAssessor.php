<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MachineStateCategory;
use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;

readonly class GetMachineReadinessAssessor implements ReadinessAssessorInterface
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
        if ($state->isEnd()) {
            return MessageHandlingReadiness::NEVER;
        }

        $stateCategory = MachineStateCategory::fromState($state);
        if (MachineStateCategory::PRE_ACTIVE !== $stateCategory) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
