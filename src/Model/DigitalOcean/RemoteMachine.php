<?php

namespace App\Model\DigitalOcean;

use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Model\RemoteMachineInterface;
use App\Services\MachineManager\DigitalOcean\Entity\Droplet;

class RemoteMachine implements RemoteMachineInterface
{
    public const STATE_NEW = 'new';
    public const STATE_ACTIVE = 'active';

    public function __construct(
        private Droplet $droplet
    ) {
    }

    public function getProvider(): MachineProvider
    {
        return MachineProvider::DIGITALOCEAN;
    }

    public function getId(): int
    {
        return $this->droplet->id;
    }

    /**
     * @return string[]
     */
    public function getIpAddresses(): array
    {
        return $this->droplet->networks->getPublicIpv4Addresses();
    }

    /**
     * @return null|MachineState::UP_ACTIVE|MachineState::UP_STARTED
     */
    public function getState(): ?MachineState
    {
        if (self::STATE_NEW === $this->droplet->status) {
            return MachineState::UP_STARTED;
        }

        if (self::STATE_ACTIVE === $this->droplet->status) {
            return MachineState::UP_ACTIVE;
        }

        return null;
    }
}
