<?php

namespace App\Exception\MachineProvider;

use App\Exception\AbstractMachineException;
use App\Model\MachineActionInterface;

class Exception extends AbstractMachineException implements ExceptionInterface
{
    /**
     * @param MachineActionInterface::ACTION_* $action
     */
    public function __construct(
        string $machineId,
        private string $action,
        private \Throwable $remoteException,
        int $code = 0
    ) {
        parent::__construct($machineId, self::createMessage($machineId, $action), $code, $remoteException);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getRemoteException(): \Throwable
    {
        return $this->remoteException;
    }

    private static function createMessage(string $machineId, string $action): string
    {
        $className = '';
        $classNameParts = explode('\\', static::class);
        if (is_array($classNameParts)) {
            $className = array_pop($classNameParts);
        }

        return sprintf(
            '%s Unable to perform action "%s" for resource "%s"',
            $className,
            $action,
            $machineId
        );
    }
}
