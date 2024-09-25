<?php

namespace App\Services\MachineManager\DigitalOcean;

use App\Exception\NoDigitalOceanClientException;
use App\Exception\Stack;
use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;

readonly class ClientPool
{
    /**
     * @param non-empty-array<Client> $clients
     */
    public function __construct(private array $clients)
    {
    }

    /**
     * @throws NoDigitalOceanClientException
     */
    public function droplet(): Droplet
    {
        return $this->findClient()->droplet();
    }

    /**
     * @throws NoDigitalOceanClientException
     */
    private function findClient(): Client
    {
        $exceptions = [];

        foreach ($this->clients as $client) {
            try {
                $client->droplet()->getById(0);

                return $client;
            } catch (ExceptionInterface $exception) {
                if ($exception instanceof RuntimeException && 404 === $exception->getCode()) {
                    return $client;
                }

                $exceptions[] = $exception;
            }
        }

        throw new NoDigitalOceanClientException(new Stack($exceptions));
    }
}
