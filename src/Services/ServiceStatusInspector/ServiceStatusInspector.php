<?php

namespace App\Services\ServiceStatusInspector;

use App\Exception\LoggableException;
use Psr\Log\LoggerInterface;

class ServiceStatusInspector extends GenericServiceStatusInspector
{
    /**
     * @param ComponentInspectorInterface[] $componentInspectors
     */
    public function __construct(
        array $componentInspectors,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $componentInspectors,
            [
                function (\Throwable $exception) use ($logger) {
                    $logger->error((string) (new LoggableException($exception)));
                },
            ],
        );
    }
}
