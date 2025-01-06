<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnrecoverableExceptionInterface;
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
            MachineAction::CREATE,
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

    public function testIsUnrecoverable(): void
    {
        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $this->exception);
    }
}
