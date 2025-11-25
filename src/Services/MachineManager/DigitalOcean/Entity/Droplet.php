<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class Droplet
{
    /**
     * @param positive-int     $id
     * @param non-empty-string $status
     */
    public function __construct(
        public int $id,
        public string $status,
        public NetworkCollection $networks,
    ) {}
}
