<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class DropletCollection
{
    /**
     * @param Droplet[] $droplets
     */
    public function __construct(
        public array $droplets,
    ) {
    }

    public function first(): ?Droplet
    {
        return $this->droplets[0] ?? null;
    }
}
