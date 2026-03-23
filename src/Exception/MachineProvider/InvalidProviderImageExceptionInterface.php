<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;

interface InvalidProviderImageExceptionInterface extends
    ExceptionInterface,
    UnrecoverableExceptionInterface,
    HasMachineProviderInterface
{
    public function getImage(): string;
}
