<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\NoDigitalOceanClientException;
use App\Services\DigitalOceanClientPool;
use App\Tests\AbstractBaseFunctionalTest;
use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class DigitalOceanClientPoolTest extends AbstractBaseFunctionalTest
{
    public function testServiceExistsInContainer(): void
    {
        self::assertInstanceOf(
            DigitalOceanClientPool::class,
            self::getContainer()->get(DigitalOceanClientPool::class)
        );
    }

    /**
     * @dataProvider getNoClientDataProvider
     *
     * @param array<non-empty-string, Client> $clients
     */
    public function testGetNoClient(array $clients): void
    {
        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $clientPool = new DigitalOceanClientPool($clients, $logger);

        self::expectException(NoDigitalOceanClientException::class);

        $clientPool->get();
    }

    /**
     * @return array<mixed>
     */
    public function getNoClientDataProvider(): array
    {
        $nonAuthenticatingDropletApi1 = \Mockery::mock(Droplet::class);
        $nonAuthenticatingDropletApi1
            ->shouldReceive('getById')
            ->with(0)
            ->andThrow(new RuntimeException('Unable to authenticate you.', 401))
        ;

        $nonAuthenticatingDropletApi2 = \Mockery::mock(Droplet::class);
        $nonAuthenticatingDropletApi2
            ->shouldReceive('getById')
            ->with(0)
            ->andThrow(new RuntimeException('Unable to authenticate you.', 401))
        ;

        $nonAuthenticatingClient1 = \Mockery::mock(Client::class);
        $nonAuthenticatingClient1
            ->shouldReceive('droplet')
            ->andReturn($nonAuthenticatingDropletApi1)
        ;

        $nonAuthenticatingClient2 = \Mockery::mock(Client::class);
        $nonAuthenticatingClient2
            ->shouldReceive('droplet')
            ->andReturn($nonAuthenticatingDropletApi2)
        ;

        return [
            'no clients' => [
                'clients' => [],
            ],
            'single client, unable to authenticate' => [
                'clients' => [
                    'non-authenticating-1' => $nonAuthenticatingClient1,
                ],
            ],
            'two clients, neither able to authenticate' => [
                'clients' => [
                    'non-authenticating-1' => $nonAuthenticatingClient1,
                    'non-authenticating-2' => $nonAuthenticatingClient2,
                ],
            ],
        ];
    }

    /**
     * @dataProvider getSuccessDataProvider
     *
     * @param array<non-empty-string, Client> $clients
     */
    public function testGetSuccess(array $clients, Client $expected): void
    {
        $logger = self::getContainer()->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $clientPool = new DigitalOceanClientPool($clients, $logger);

        self::assertSame($expected, $clientPool->get());
    }

    /**
     * @return array<mixed>
     */
    public function getSuccessDataProvider(): array
    {
        $nonAuthenticatingDropletApi1 = \Mockery::mock(Droplet::class);
        $nonAuthenticatingDropletApi1
            ->shouldReceive('getById')
            ->with(0)
            ->andThrow(new RuntimeException('Unable to authenticate you.', 401))
        ;

        $nonAuthenticatingDropletApi2 = \Mockery::mock(Droplet::class);
        $nonAuthenticatingDropletApi2
            ->shouldReceive('getById')
            ->with(0)
            ->andThrow(new RuntimeException('Unable to authenticate you.', 401))
        ;

        $authenticatingDropletApi = \Mockery::mock(Droplet::class);
        $authenticatingDropletApi
            ->shouldReceive('getById')
            ->with(0)
            ->andThrow(new RuntimeException('The resource you requested could not be found.', 404))
        ;

        $nonAuthenticatingClient1 = \Mockery::mock(Client::class);
        $nonAuthenticatingClient1
            ->shouldReceive('droplet')
            ->andReturn($nonAuthenticatingDropletApi1)
        ;

        $nonAuthenticatingClient2 = \Mockery::mock(Client::class);
        $nonAuthenticatingClient2
            ->shouldReceive('droplet')
            ->andReturn($nonAuthenticatingDropletApi2)
        ;

        $authenticatingClient = \Mockery::mock(Client::class);
        $authenticatingClient
            ->shouldReceive('droplet')
            ->andReturn($authenticatingDropletApi)
        ;

        return [
            'single client, authenticated' => [
                'clients' => [
                    'authenticating' => $authenticatingClient,
                ],
                'expected' => $authenticatingClient,
            ],
            'multiple clients, authenticated eventually' => [
                'clients' => [
                    'non-authenticating-1' => $nonAuthenticatingClient1,
                    'non-authenticating-2' => $nonAuthenticatingClient2,
                    'authenticating' => $authenticatingClient,
                ],
                'expected' => $authenticatingClient,
            ],
        ];
    }
}
