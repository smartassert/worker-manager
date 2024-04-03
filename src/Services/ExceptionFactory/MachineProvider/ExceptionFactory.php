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
    private array $factories = [];

    /**
     * @param ExceptionFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        foreach ($factories as $factory) {
            if ($factory instanceof ExceptionFactoryInterface) {
                $this->factories[] = $factory;
            }
        }
    }

    /**
     * @param non-empty-string $resourceId
     */
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
