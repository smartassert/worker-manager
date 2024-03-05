<?php

namespace App\Model;

use App\Enum\MachineState;

interface RemoteMachineInterface
{
    /**
     * @return non-empty-string
     */
    public function getProvider(): string;

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
