<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Message\GetMachine;
use App\Message\RemoteMachineMessageInterface;
use App\Model\ProviderInterface;
use App\Services\RemoteRequestRetryDecider;
use App\Tests\AbstractBaseFunctionalTest;
use DigitalOceanV2\Exception\ApiLimitExceededException;
use DigitalOceanV2\Exception\InvalidArgumentException;

class RemoteRequestRetryDeciderTest extends AbstractBaseFunctionalTest
{
    private RemoteRequestRetryDecider $decider;

    protected function setUp(): void
    {
        parent::setUp();

        $decider = self::$container->get(RemoteRequestRetryDecider::class);
        if ($decider instanceof RemoteRequestRetryDecider) {
            $this->decider = $decider;
        }
    }

    /**
     * @dataProvider decideDataProvider
     *
     * @param ProviderInterface::NAME_* $provider
     */
    public function testDecide(
        string $provider,
        RemoteMachineMessageInterface $request,
        \Throwable $exception,
        bool $expectedDecision
    ): void {
        self::assertSame($expectedDecision, $this->decider->decide($provider, $request, $exception));
    }

    /**
     * @return array<mixed>
     */
    public function decideDataProvider(): array
    {
        return [
            'digitalocean ' . ApiLimitExceededException::class => [
                'provider' => ProviderInterface::NAME_DIGITALOCEAN,
                'request' => new GetMachine('id0', 'id'),
                'exception' => new ApiLimitExceededException(),
                'expectedDecision' => false,
            ],
            'digitalocean ' . InvalidArgumentException::class => [
                'provider' => ProviderInterface::NAME_DIGITALOCEAN,
                'request' => new GetMachine('id0', 'id'),
                'exception' => new InvalidArgumentException(),
                'expectedDecision' => true,
            ],
        ];
    }
}
