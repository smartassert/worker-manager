<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

use App\Services\MachineManager\DigitalOcean\Entity\Error;

class ApiLimitExceededException extends \Exception
{
    public function __construct(
        public readonly Error $error,
        public readonly int $rateLimitReset,
        public readonly int $rateLimitRemaining,
        public readonly int $rateLimitLimit,
    ) {
        parent::__construct($error->message, $error->code);
    }
}
