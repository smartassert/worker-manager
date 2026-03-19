<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;

interface InvalidEntityResponseExceptionInterface extends
    ExceptionInterface,
    UnrecoverableExceptionInterface,
    HasMachineProviderInterface
{
    /**
     * @return array<mixed>
     */
    public function getData(): array;
}
