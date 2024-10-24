<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use App\Enum\MachineStateCategory;
use Symfony\Component\HttpFoundation\JsonResponse;

class MachineResponse extends JsonResponse
{
    public function __construct(Machine $machine, ?ActionFailure $actionFailure = null, int $statusCode = 200)
    {
        $stateCategory = $machine->getStateCategory();

        parent::__construct(
            [
                'id' => $machine->getId(),
                'state' => $machine->getState(),
                'ip_addresses' => $machine->getIpAddresses(),
                'state_category' => $stateCategory,
                'action_failure' => $actionFailure,
                'has_failed_state' => $machine->hasFailedState(),
                'has_active_state' => MachineStateCategory::ACTIVE === $stateCategory,
                'has_ending_state' => MachineStateCategory::ENDING === $stateCategory,
                'has_end_state' => MachineStateCategory::END === $stateCategory,
            ],
            $statusCode
        );
    }
}
