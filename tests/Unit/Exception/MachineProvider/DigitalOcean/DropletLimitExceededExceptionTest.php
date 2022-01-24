<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider\DigitalOcean;

use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
use App\Model\MachineActionInterface;
use DigitalOceanV2\Exception\ValidationFailedException;
use PHPUnit\Framework\TestCase;

class DropletLimitExceededExceptionTest extends TestCase
{
    private DropletLimitExceededException $exception;

    protected function setUp(): void
    {
        $providerException = new ValidationFailedException(
            'creating this/these droplet(s) will exceed your droplet limit',
            422
        );

        $this->exception = new DropletLimitExceededException(
            'machine id',
            MachineActionInterface::ACTION_CREATE,
            $providerException
        );
    }

    public function testGetCode(): void
    {
        self::assertSame(
            UnprocessableRequestExceptionInterface::CODE_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED,
            $this->exception->getCode()
        );
    }

    public function testGetReason(): void
    {
        self::assertSame(
            UnprocessableRequestExceptionInterface::REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED,
            $this->exception->getReason()
        );
    }

    public function testIsUnrecoverable(): void
    {
        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $this->exception);
    }
}
