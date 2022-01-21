<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\RemoteRequestRetryDecider\DigitalOcean;

use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use App\Services\RemoteRequestRetryDecider\DigitalOcean\DigitalOceanRemoteRequestRetryDecider;
use DigitalOceanV2\Exception\ApiLimitExceededException;
use DigitalOceanV2\Exception\DiscoveryFailedException;
use DigitalOceanV2\Exception\ErrorException;
use DigitalOceanV2\Exception\InvalidArgumentException;
use DigitalOceanV2\Exception\InvalidRecordException;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;
use PHPUnit\Framework\TestCase;

class DigitalOceanRemoteRequestRetryDeciderTest extends TestCase
{
    private DigitalOceanRemoteRequestRetryDecider $decider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decider = new DigitalOceanRemoteRequestRetryDecider();
    }

    public function testHandles(): void
    {
        self::assertTrue($this->decider->handles(ProviderInterface::NAME_DIGITALOCEAN));
    }

    /**
     * @dataProvider decideDataProvider
     *
     * @param MachineActionInterface::ACTION_* $action
     */
    public function testDecide(\Throwable $exception, string $action, bool $expectedDecision): void
    {
        self::assertSame($expectedDecision, $this->decider->decide($action, $exception));
    }

    /**
     * @return array<mixed>
     */
    public function decideDataProvider(): array
    {
        return [
            ApiLimitExceededException::class => [
                'exception' => new ApiLimitExceededException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => false,
            ],
            ValidationFailedException::class => [
                'exception' => new ValidationFailedException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            RuntimeException::class . ' non-401' => [
                'exception' => new RuntimeException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            RuntimeException::class . ' 401' => [
                'exception' => new RuntimeException('message', 401),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => false,
            ],
            RuntimeException::class . ' 404, CREATE' => [
                'exception' => new RuntimeException('message', 404),
                'action' => MachineActionInterface::ACTION_CREATE,
                'expectedDecision' => false,
            ],
            RuntimeException::class . ' 404, GET' => [
                'exception' => new RuntimeException('message', 404),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            DiscoveryFailedException::class => [
                'exception' => new DiscoveryFailedException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            ErrorException::class => [
                'exception' => new ErrorException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            InvalidRecordException::class => [
                'exception' => new InvalidRecordException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            InvalidArgumentException::class => [
                'exception' => new InvalidArgumentException(),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => true,
            ],
            DropletLimitExceededException::class => [
                'exception' => \Mockery::mock(DropletLimitExceededException::class),
                'action' => MachineActionInterface::ACTION_GET,
                'expectedDecision' => false,
            ],
        ];
    }
}
