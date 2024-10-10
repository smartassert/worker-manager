<?php

declare(strict_types=1);

namespace App\Response;

use App\Entity\ActionFailure;
use App\Entity\Machine;
use Symfony\Component\HttpFoundation\JsonResponse;

class MachineResponse extends JsonResponse
{
    public function __construct(Machine $machine, ?ActionFailure $actionFailure = null, int $statusCode = 200)
    {
        parent::__construct(
            [
                'id' => $machine->getId(),
                'state' => $machine->getState(),
                'ip_addresses' => $machine->getIpAddresses(),
                'state_category' => $machine->getStateCategory(),
                'action_failure' => $actionFailure,
            ],
            $statusCode
        );
    }
}
