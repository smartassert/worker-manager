<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MachineState;
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
        if (MachineState::isEnd($state)) {
            return MessageHandlingReadiness::NEVER;
        }

        if (!MachineState::isPreActive($state)) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
