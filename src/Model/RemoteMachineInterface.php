<?php

namespace App\Model;

use App\Enum\MachineProvider;
use App\Enum\MachineState;

interface RemoteMachineInterface
{
    public function getProvider(): MachineProvider;

    public function getId(): int;

    /**
     * @return string[]
     */
    public function getIpAddresses(): array;

    /**
     * @return null|MachineState::UP_ACTIVE|MachineState::UP_STARTED
     */
    public function getState(): ?MachineState;
}
