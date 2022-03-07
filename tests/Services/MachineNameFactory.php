<?php

declare(strict_types=1);

namespace App\Tests\Services;

class MachineNameFactory
{
    public function __construct(
        private string $machineNamePrefix,
        private string $digitaloceanDropletTag,
    ) {
    }

    public function create(string $machineId): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->machineNamePrefix,
            $this->digitaloceanDropletTag,
            $machineId
        );
    }
}
