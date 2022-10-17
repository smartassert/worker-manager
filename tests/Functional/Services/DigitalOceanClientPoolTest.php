<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Exception\NoDigitalOceanClientException;
use App\Services\DigitalOceanClientPool;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\DataProvider\RemoteRequestThrowsExceptionDataProviderTrait;
use DigitalOceanV2\Api\Droplet;
use DigitalOceanV2\Client;
use DigitalOceanV2\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class DigitalOceanClientPoolTest extends AbstractBaseFunctionalTest
{
    use RemoteRequestThrowsExceptionDataProviderTrait;

    private DigitalOceanClientPool $digitalOceanClientPool;

    protected function setUp(): void
    {
        parent::setUp();

        $digitalOceanClientPool = self::getContainer()->get(DigitalOceanClientPool::class);
        \assert($digitalOceanClientPool instanceof DigitalOceanClientPool);
        $this->digitalOceanClientPool = $digitalOceanClientPool;
    }

    public function testClientConfiguration(): void
    {
        $clientIds = $this->digitalOceanClientPool->getClientServiceIds();
        self::assertSame(
            [
                'app.digitalocean.client.primary',
                'app.digitalocean.client.secondary',
            ],
            $clientIds,
        );

        foreach ($clientIds as $clientId) {
            $client = self::getContainer()->get($clientId);
            self::assertInstanceOf(Client::class, $client);
        }
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

        $clientPool = new DigitalOceanClientPool([$clients], $logger);

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
                    $nonAuthenticatingClient1,
                ],
            ],
            'two clients, neither able to authenticate' => [
                'clients' => [
                    $nonAuthenticatingClient1,
                    $nonAuthenticatingClient2,
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

        $clientPool = new DigitalOceanClientPool([$clients], $logger);

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
                    $authenticatingClient,
                ],
                'expected' => $authenticatingClient,
            ],
            'multiple clients, authenticated eventually' => [
                'clients' => [
                    $nonAuthenticatingClient1,
                    $nonAuthenticatingClient2,
                    $authenticatingClient,
                ],
                'expected' => $authenticatingClient,
            ],
        ];
    }
}
