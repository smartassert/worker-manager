<?php

namespace App\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\UnknownException;

class ExceptionFactory
{
    /**
     * @var ExceptionFactoryInterface[]
     */
    private array $factories;

    /**
     * @param ExceptionFactoryInterface[] $factories
     */
    public function __construct(array $factories)
    {
        $this->factories = array_filter($factories, function ($value) {
            return $value instanceof ExceptionFactoryInterface;
        });
    }

    public function create(string $resourceId, MachineAction $action, \Throwable $exception): ExceptionInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->handles($exception)) {
                $newException = $factory->create($resourceId, $action, $exception);

                if ($newException instanceof ExceptionInterface) {
                    return $newException;
                }
            }
        }

        return new UnknownException($resourceId, $action, $exception);
    }
}
