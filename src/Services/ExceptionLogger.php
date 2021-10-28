<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use SmartAssert\InvokableLogger\LoggableException;

class ExceptionLogger
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function log(\Throwable $exception): void
    {
        $this->logger->error((string) (new LoggableException($exception)));
    }
}
