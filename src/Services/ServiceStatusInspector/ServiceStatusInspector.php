<?php

namespace App\Services\ServiceStatusInspector;

use App\Exception\LoggableException;
use Psr\Log\LoggerInterface;

class ServiceStatusInspector implements ServiceStatusInspectorInterface
{
    /**
     * @var ComponentInspectorInterface[]
     */
    private array $componentInspectors;

    /**
     * @var array<string, bool>
     */
    private array $componentAvailabilities = [];

    /**
     * @param ComponentInspectorInterface[] $componentInspectors
     */
    public function __construct(
        array $componentInspectors,
        private LoggerInterface $healthCheckLogger
    ) {
        foreach ($componentInspectors as $name => $componentInspector) {
            if ($componentInspector instanceof ComponentInspectorInterface) {
                $this->componentInspectors[$name] = $componentInspector;
            }
        }
    }

    public function isAvailable(): bool
    {
        $availabilities = $this->get();

        foreach ($availabilities as $availability) {
            if (false === $availability) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    public function get(): array
    {
        if ([] === $this->componentAvailabilities) {
            $this->componentAvailabilities = $this->findAvailabilities();
        }

        return $this->componentAvailabilities;
    }

    public function reset(): void
    {
        $this->componentAvailabilities = [];
    }

    /**
     * @return array<string, bool>
     */
    private function findAvailabilities(): array
    {
        $availabilities = [];

        foreach ($this->componentInspectors as $name => $componentInspector) {
            $isAvailable = true;

            try {
                ($componentInspector)();
            } catch (\Throwable $exception) {
                $isAvailable = false;
                $this->healthCheckLogger->error((string) (new LoggableException($exception)));
            }

            $availabilities[(string) $name] = $isAvailable;
        }

        return $availabilities;
    }
}
