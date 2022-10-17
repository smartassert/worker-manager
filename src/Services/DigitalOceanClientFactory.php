<?php

namespace App\Services;

use App\Exception\NoDigitalOceanClientException;
use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\ExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\HttpClient\Builder;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

class DigitalOceanClientFactory
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private readonly array $apiTokens;

    /**
     * @param array<non-empty-string, non-empty-string> $apiTokens
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        array $apiTokens,
        private readonly LoggerInterface $logger,
    ) {
        $filteredApiTokens = [];
        foreach ($apiTokens as $name => $apiToken) {
            if ('' !== trim($name) && '' !== trim($apiToken)) {
                $filteredApiTokens[$name] = $apiToken;
            }
        }

        $this->apiTokens = $filteredApiTokens;
    }

    /**
     * @throws NoDigitalOceanClientException
     */
    public function create(): Client
    {
        foreach ($this->apiTokens as $name => $token) {
            $client = new Client(new Builder($this->httpClient));
            $client->authenticate($token);

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
                        'token_name' => $name,
                    ]
                );
            }
        }

        throw new NoDigitalOceanClientException();
    }
}
