<?php

namespace App\Services\ServiceStatusInspector;

use App\Exception\LoggableException;
use Psr\Log\LoggerInterface;

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
