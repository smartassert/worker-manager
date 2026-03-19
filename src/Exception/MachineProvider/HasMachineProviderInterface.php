<?php

namespace App\Exception\MachineProvider;

use App\Enum\MachineProvider;

interface HasMachineProviderInterface
{
    public function getMachineProvider(): MachineProvider;
}
