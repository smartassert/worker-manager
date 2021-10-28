<?php

namespace App\Services\ServiceStatusInspector;

interface ServiceStatusInspectorInterface
{
    public function isAvailable(): bool;

    /**
     * Get an array of <service name>:bool
     * e.g.
     * ['service1' => true, 'service2' => false]
     * .
     *
     * @return array<string, bool>
     */
    public function get(): array;
}
