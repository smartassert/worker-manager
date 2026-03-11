<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

use App\Services\MachineManager\DigitalOcean\Entity\Error;

class ErrorException extends \Exception
{
    public function __construct(
        public Error $error,
    ) {
        parent::__construct($this->error->message, $error->code);
    }
}
