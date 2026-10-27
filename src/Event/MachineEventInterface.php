<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Machine;

interface MachineEventInterface
{
    public function getMachine(): Machine;

    /**
     * @return non-empty-string
     */
    public function getMachineId(): string;
}
