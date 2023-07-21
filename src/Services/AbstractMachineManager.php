<?php

namespace App\Services;

use App\Entity\MachineProvider;

abstract class AbstractMachineManager
{
    /**
     * @param ProviderMachineManagerInterface[] $providerMachineManagers
     */
    public function __construct(
        protected iterable $providerMachineManagers,
        private MachineNameFactory $machineNameFactory,
    ) {
    }

    protected function createMachineName(string $machineId): string
    {
        return $this->machineNameFactory->create($machineId);
    }

    protected function findProvider(MachineProvider $machineProvider): ?ProviderMachineManagerInterface
    {
        $providerName = $machineProvider->getName();

        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                if ($machineManager->getType() === $providerName) {
                    return $machineManager;
                }
            }
        }

        return null;
    }
}
