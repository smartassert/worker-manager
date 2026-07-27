<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\Machine;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

interface DeleteMachineDispatcherInterface
{
    /**
     * @throws ExceptionInterface
     */
    public function dispatchForMachine(Machine $machine): void;
}
