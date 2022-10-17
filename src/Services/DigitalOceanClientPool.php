<?php

namespace App\Services;

use App\Exception\NoDigitalOceanClientException;
use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class DigitalOceanClientPool
{
    /**
     * @var array<non-empty-string, Client>
     */
    private readonly array $clients;

    /**
     * @param array<non-empty-string, Client> $clients
     */
    public function __construct(
        array $clients,
        private readonly LoggerInterface $logger,
    ) {
        $filteredClients = [];

        foreach ($clients as $name => $client) {
            if ($client instanceof Client && '' !== trim($name)) {
                $filteredClients[$name] = $client;
            }
        }

        $this->clients = $filteredClients;
    }

    /**
     * @throws NoDigitalOceanClientException
     */
    public function get(): Client
    {
        foreach ($this->clients as $clientId => $client) {
            try {
                $client->droplet()->getById(0);

                return $client;
            } catch (ExceptionInterface $exception) {
                if ($exception instanceof RuntimeException && 404 === $exception->getCode()) {
                    return $client;
                }

                $this->logger->error(
                    $exception->getMessage(),
                    [
                        'client-id' => $clientId,
                    ]
                );
            }
        }

        throw new NoDigitalOceanClientException();
    }
}
