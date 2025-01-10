<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

class ErrorException extends \Exception
{
    public function __construct(
        public string $id,
        string $message,
        int $code
    ) {
        parent::__construct($message, $code);
    }
}
