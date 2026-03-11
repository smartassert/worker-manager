<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

use App\Services\MachineManager\DigitalOcean\Entity\Error;

class UnprocessableRequestException extends \Exception
{
    public function __construct(
        Error $error,
    ) {
        parent::__construct($error->message, $error->code);
    }
}
