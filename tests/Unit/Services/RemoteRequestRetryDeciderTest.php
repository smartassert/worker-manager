<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Message\GetMachine;
use App\Message\RemoteMachineMessageInterface;
use App\Model\ProviderInterface;
use App\Services\RemoteRequestRetryDecider;
use App\Services\RemoteRequestRetryDecider\DigitalOcean\DigitalOceanRemoteRequestRetryDecider;
use DigitalOceanV2\Exception\ApiLimitExceededException;
use DigitalOceanV2\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RemoteRequestRetryDeciderTest extends TestCase
{
    /**
     * @dataProvider decideDataProvider
     *
     * @param ProviderInterface::NAME_* $provider
     */
    public function testDecide(
        RemoteRequestRetryDecider $decider,
        string $provider,
        RemoteMachineMessageInterface $request,
        \Throwable $exception,
        bool $expectedDecision
    ): void {
        self::assertSame($expectedDecision, $decider->decide($provider, $request, $exception));
    }

    /**
     * @return array[]
     */
    public function decideDataProvider(): array
    {
        return [
            'no deciders' => [
                'decider' => new RemoteRequestRetryDecider([]),
                'provider' => ProviderInterface::NAME_DIGITALOCEAN,
                'request' => new GetMachine('request-id', 'machine-id'),
                'exception' => new \Exception(),
                'expectedDecision' => false,
            ],
            'has decider, decider: false' => [
                'decider' => new RemoteRequestRetryDecider([new DigitalOceanRemoteRequestRetryDecider()]),
                'provider' => ProviderInterface::NAME_DIGITALOCEAN,
                'request' => new GetMachine('request-id', 'machine-id'),
                'exception' => new ApiLimitExceededException(),
                'expectedDecision' => false,
            ],
            'has decider, decider: true' => [
                'decider' => new RemoteRequestRetryDecider([new DigitalOceanRemoteRequestRetryDecider()]),
                'provider' => ProviderInterface::NAME_DIGITALOCEAN,
                'request' => new GetMachine('request-id', 'machine-id'),
                'exception' => new InvalidArgumentException(),
                'expectedDecision' => true,
            ],
        ];
    }
}
