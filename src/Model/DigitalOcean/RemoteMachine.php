<?php

namespace App\Model\DigitalOcean;

use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Model\RemoteMachineInterface;
use DigitalOceanV2\Entity\Droplet as DropletEntity;

class RemoteMachine implements RemoteMachineInterface
{
    public const STATE_NEW = 'new';
    public const STATE_ACTIVE = 'active';
    public const TYPE = 'digitalocean';

    public function __construct(
        private DropletEntity $droplet
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
        $dropletNetworks = $this->droplet->networks;
        $ipAddresses = [];
        foreach ($dropletNetworks as $dropletNetwork) {
            $network = new Network($dropletNetwork);
            $networkIp = $network->getPublicIpv4Address();

            if (is_string($networkIp)) {
                $ipAddresses[] = $networkIp;
            }
        }

        return $ipAddresses;
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
