<?php

namespace App\Exception\MachineProvider;

class ProviderMachineNotFoundException extends \Exception
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
