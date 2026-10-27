<?php

declare(strict_types=1);

namespace App\Event;

interface MachineEventInterface
{
    /**
     * @return non-empty-string
     */
    public function getMachineId(): string;
}
