<?php

declare(strict_types=1);

namespace App\Message;

interface MachineRequestInterface extends UniqueRequestInterface
{
    public function getMachineId(): string;
}
