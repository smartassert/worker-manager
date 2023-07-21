<?php

namespace App\Services;

use App\Exception\MachineNotFindableException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Model\RemoteMachineInterface;

class RemoteMachineFinder extends AbstractMachineManager
{
    /**
     * @throws MachineNotFindableException
     * @throws \Throwable
     */
    public function find(string $machineId): ?RemoteMachineInterface
    {
        $machineName = $this->createMachineName($machineId);

        $exceptionStack = [];
        foreach ($this->providerMachineManagers as $machineManager) {
            if ($machineManager instanceof ProviderMachineManagerInterface) {
                try {
                    $remoteMachine = $machineManager->get($machineId, $machineName);

                    if ($remoteMachine instanceof RemoteMachineInterface) {
                        return $remoteMachine;
                    }
                } catch (ExceptionInterface $exception) {
                    $exceptionStack[] = $exception;
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineNotFindableException($machineId, $exceptionStack);
        }

        return null;
    }
}
