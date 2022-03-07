<?php

declare(strict_types=1);

namespace App\Tests\Proxy\DigitalOceanV2\Api;

use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use Mockery\MockInterface;

class DropletProxy extends Droplet
{
    private Droplet $mock;

    public function __construct()
    {
        parent::__construct(\Mockery::mock(Client::class));

        $this->mock = \Mockery::mock(Droplet::class);
    }

    public function withGetByIdCall(int $id, object $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('getById')
            ->with($id)
        ;

        if ($outcome instanceof \Exception) {
            $expectation
                ->andThrow($outcome)
            ;
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
    }

    public function getById(int $id)
    {
        return $this->mock->getById($id);
    }
}
