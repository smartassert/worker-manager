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
     * @param array<int, iterable<non-empty-string, Client>> $taggedClients
     */
    public function __construct(
        array $taggedClients,
        private readonly LoggerInterface $logger,
    ) {
        $clients = [];

        foreach ($taggedClients as $clientCollection) {
            foreach ($clientCollection as $key => $value) {
                if ($value instanceof Client) {
                    $clients[$key] = $value;
                }
            }
        }

        $this->clients = $clients;
    }

    /**
     * @return string[]
     */
    public function getClientServiceIds(): array
    {
        return array_keys($this->clients);
    }

    /**
     * @throws NoDigitalOceanClientException
     */
    public function get(): Client
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client instanceof Client) {
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
        }

        throw new NoDigitalOceanClientException();
    }
}
