<?php

namespace App\Services;

use DigitalOceanV2\Client;
use DigitalOceanV2\HttpClient\Builder;
use Psr\Http\Client\ClientInterface;

class DigitalOceanClientFactory
{
    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {
    }

    public function create(string $apiToken): Client
    {
        $builder = new Builder($this->httpClient);
        $client = new Client($builder);

        $client->authenticate($apiToken);

        return $client;
    }
}
