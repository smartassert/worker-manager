<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class GetMachineEvent extends Event implements MachineEventInterface
{
    public function __construct(
        private readonly Machine $machine,
    ) {}

    public function getMachine(): Machine
    {
        return $this->machine;
    }
}
