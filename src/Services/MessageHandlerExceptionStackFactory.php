<?php

namespace App\Services;

use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\StackedExceptionInterface;

class MessageHandlerExceptionStackFactory
{
    /**
     * @return \Throwable[]
     */
    public function create(\Throwable $throwable): array
    {
        $stack = [
            $throwable,
        ];

        if ($throwable instanceof ExceptionInterface) {
            $stack[] = $throwable->getRemoteException();
        }

        if ($throwable instanceof StackedExceptionInterface) {
            foreach ($throwable->getExceptionStack() as $encapsulatedException) {
                $stack = array_merge($stack, $this->create($encapsulatedException));
            }
        }

        return $stack;
    }
}
