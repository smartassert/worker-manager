<?php

declare(strict_types=1);

namespace App\Tests\Proxy\DigitalOceanV2;

use App\Tests\Proxy\DigitalOceanV2\Api\DropletApiProxy;
use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use Psr\Http\Message\ResponseInterface;

class ClientProxy extends Client
{
    private ?ResponseInterface $lastResponse = null;

    public function __construct(
        private readonly DropletApiProxy $dropletApiProxy,
    ) {
        parent::__construct();
    }

    public function droplet(): Droplet
    {
        return $this->dropletApiProxy;
    }

    public function setLastResponse(ResponseInterface $response): void
    {
        $this->lastResponse = $response;
    }

    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse;
    }
}
