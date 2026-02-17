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

        $hasFailedState = $machine->hasFailedState();
        $hasEndState = MachineStateCategory::END === $stateCategory;
        $hasSucceeded = $hasEndState && false === $hasFailedState;

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
                'has_end_state' => $hasEndState,
                'meta_state' => [
                    'ended' => $hasEndState,
                    'succeeded' => $hasSucceeded,
                ],
            ],
            $statusCode
        );
    }
}
