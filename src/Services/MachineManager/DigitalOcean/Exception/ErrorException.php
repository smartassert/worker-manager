<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

use App\Services\MachineManager\DigitalOcean\Entity\Error;
use App\Services\MachineManager\DigitalOcean\Request\RequestInterface;

class ErrorException extends \Exception
{
    public function __construct(
        public Error $error,
        public RequestInterface $request,
    ) {
        parent::__construct($this->error->message, $error->code);
    }
}
