<?php

namespace App\Services;

use App\Exception\MachineNotRemovableException;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\UnknownRemoteMachineExceptionInterface;

class RemoteMachineRemover extends AbstractMachineManager
{
    /**
     * @throws MachineNotRemovableException
     * @throws \Throwable
     */
    public function remove(string $machineId): void
    {
        $machineName = $this->createMachineName($machineId);

        $exceptionStack = [];
        $remoteMachine = null;
        foreach ($this->machineManagerStack->getManagers() as $machineManager) {
            if (null === $remoteMachine) {
                try {
                    $machineManager->remove($machineId, $machineName);
                } catch (ExceptionInterface $exception) {
                    if (!$exception instanceof UnknownRemoteMachineExceptionInterface) {
                        $exceptionStack[] = $exception;
                    }
                }
            }
        }

        if ([] !== $exceptionStack) {
            throw new MachineNotRemovableException($machineId, $exceptionStack);
        }
    }
}
