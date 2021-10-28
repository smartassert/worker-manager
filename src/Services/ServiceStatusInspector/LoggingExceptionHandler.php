<?php

namespace App\Services\ServiceStatusInspector;

use Psr\Log\LoggerInterface;
use SmartAssert\InvokableLogger\LoggableException;

class LoggingExceptionHandler
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(\Throwable $exception): void
    {
        $this->logger->error((string) (new LoggableException($exception)));
    }
}
