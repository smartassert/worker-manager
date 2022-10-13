<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class MachineRequestResponse extends JsonResponse
{
    public function __construct(string $machineId, string $requestedAction, string $statusUrl)
    {
        parent::__construct(
            [
                'machine_id' => $machineId,
                'requested_action' => $requestedAction,
                'status_url' => $statusUrl,
            ],
            202
        );
    }
}
