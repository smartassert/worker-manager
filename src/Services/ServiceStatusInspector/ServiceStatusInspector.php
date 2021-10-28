<?php

namespace App\Services\ServiceStatusInspector;

class ServiceStatusInspector extends GenericServiceStatusInspector
{
    /**
     * @param ComponentInspectorInterface[] $componentInspectors
     */
    public function __construct(
        array $componentInspectors,
        LoggingExceptionHandler $exceptionHandler,
    ) {
        parent::__construct(
            $componentInspectors,
            [
                $exceptionHandler,
            ],
        );
    }
}
