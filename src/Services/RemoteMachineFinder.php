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
        $remoteMachine = null;
        foreach ($this->machineManagerStack->getManagers() as $machineManager) {
            if (null === $remoteMachine) {
                try {
                    $remoteMachine = $machineManager->get($machineId, $machineName);
                } catch (ExceptionInterface $exception) {
                    $exceptionStack[] = $exception;
                }

                if ($remoteMachine instanceof RemoteMachineInterface) {
                    return $remoteMachine;
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineNotFindableException($machineId, $exceptionStack);
        }

        return null;
    }
}
