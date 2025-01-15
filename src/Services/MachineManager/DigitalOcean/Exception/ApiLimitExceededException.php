<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

class ApiLimitExceededException extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $rateLimitReset,
        public readonly int $rateLimitRemaining,
        public readonly int $rateLimitLimit,
    ) {
        parent::__construct($message, 429);
    }
}
