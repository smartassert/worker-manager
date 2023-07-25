<?php

declare(strict_types=1);

namespace App\Message;

interface MachineRequestInterface extends UniqueRequestInterface
{
    /**
     * @return non-empty-string
     */
    public function getMachineId(): string;
}
