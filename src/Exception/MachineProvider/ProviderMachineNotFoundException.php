<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;

class ProviderMachineNotFoundException extends \Exception implements UnrecoverableExceptionInterface
{
    /**
     * @param non-empty-string $machineId
     * @param non-empty-string $providerName
     */
    public function __construct(string $machineId, string $providerName)
    {
        parent::__construct(sprintf('Machine "%s" not found with provider "%s"', $machineId, $providerName));
    }
}
