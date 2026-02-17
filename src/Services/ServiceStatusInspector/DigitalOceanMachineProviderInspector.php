<?php

namespace App\Services\ServiceStatusInspector;

use App\Services\MachineManager\DigitalOcean\Client\Client;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\InvalidEntityDataException;
use Psr\Http\Client\ClientExceptionInterface;
use SmartAssert\ServiceStatusInspector\ComponentStatusInspectorInterface;

class DigitalOceanMachineProviderInspector implements ComponentStatusInspectorInterface
{
    public const DROPLET_NAME = 'null_name';
    public const DEFAULT_IDENTIFIER = 'machine_provider_digital_ocean';

    public function __construct(
        private readonly Client $digitalOceanClient,
        private readonly string $identifier = self::DEFAULT_IDENTIFIER,
    ) {}

    /**
     * @throws AuthenticationException
     * @throws ErrorException
     * @throws InvalidEntityDataException
     * @throws ClientExceptionInterface
     */
    public function getStatus(): bool|string
    {
        try {
            $this->digitalOceanClient->getDroplet(self::DROPLET_NAME);
        } catch (EmptyDropletCollectionException) {
            return true;
        } catch  (\Throwable $exception) {
            return $exception::class;
        }

        return true;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
