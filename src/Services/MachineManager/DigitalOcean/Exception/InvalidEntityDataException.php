<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

class InvalidEntityDataException extends \Exception
{
    /**
     * @param non-empty-string $type
     * @param array<mixed>     $data
     */
    public function __construct(
        public string $type,
        public array $data,
    ) {
        parent::__construct(sprintf('Invalid "%s" entity data', $type));
    }
}
