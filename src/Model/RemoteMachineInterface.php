<?php

namespace App\Model;

use App\Entity\Machine;

interface RemoteMachineInterface
{
    /**
     * @return ProviderInterface::NAME_*
     */
    public function getProvider(): string;

    public function getId(): int;

    /**
     * @return string[]
     */
    public function getIpAddresses(): array;

    /**
     * @return null|Machine::STATE_UP_ACTIVE|Machine::STATE_UP_STARTED
     */
    public function getState(): ?string;
}
