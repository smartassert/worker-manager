<?php

namespace App\Services\MachineManager\DigitalOcean\Entity;

readonly class Error
{
    public function __construct(
        public int $code,
        public string $id,
        public string $message,
    ) {}
}
