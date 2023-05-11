<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\AbstractMachineException;

class Exception extends AbstractMachineException implements ExceptionInterface
{
    public function __construct(
        string $machineId,
        private MachineAction $action,
        private \Throwable $remoteException,
        int $code = 0
    ) {
        parent::__construct($machineId, self::createMessage($machineId, $action), $code, $remoteException);
    }

    public function getAction(): MachineAction
    {
        return $this->action;
    }

    public function getRemoteException(): \Throwable
    {
        return $this->remoteException;
    }

    private static function createMessage(string $machineId, MachineAction $action): string
    {
        $className = '';
        $classNameParts = explode('\\', static::class);
        if (is_array($classNameParts)) {
            $className = array_pop($classNameParts);
        }

        return sprintf(
            '%s Unable to perform action "%s" for resource "%s"',
            $className,
            $action->value,
            $machineId
        );
    }
}
