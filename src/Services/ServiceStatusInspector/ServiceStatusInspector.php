<?php

namespace App\Services\ServiceStatusInspector;

class ServiceStatusInspector implements ServiceStatusInspectorInterface
{
    /**
     * @var array<int|string, callable>
     */
    private array $componentInspectors = [];

    /**
     * @var array<string, bool>
     */
    private array $componentAvailabilities = [];

    /**
     * @var callable[]
     */
    private array $exceptionHandlers = [];

    /**
     * @param callable[] $componentInspectors
     * @param callable[] $exceptionHandlers
     */
    public function __construct(
        array $componentInspectors,
        array $exceptionHandlers = [],
    ) {
        foreach ($componentInspectors as $name => $componentInspector) {
            if (is_callable($componentInspector)) {
                $this->componentInspectors[$name] = $componentInspector;
            }
        }

        foreach ($exceptionHandlers as $exceptionHandler) {
            if (is_callable($exceptionHandler)) {
                $this->exceptionHandlers[] = $exceptionHandler;
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

                foreach ($this->exceptionHandlers as $exceptionHandler) {
                    ($exceptionHandler)($exception);
                }
            }

            $availabilities[(string) $name] = $isAvailable;
        }

        return $availabilities;
    }
}
