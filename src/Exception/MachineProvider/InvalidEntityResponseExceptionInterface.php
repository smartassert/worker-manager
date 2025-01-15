<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;
use App\Exception\UnrecoverableExceptionInterface;

interface InvalidEntityResponseExceptionInterface extends ExceptionInterface, UnrecoverableExceptionInterface
{
    /**
     * @return array<mixed>
     */
    public function getData(): array;

    public function getMachineProvider(): MachineProvider;
}
