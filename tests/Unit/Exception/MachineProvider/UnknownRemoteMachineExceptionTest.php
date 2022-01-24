<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider;

use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\UnrecoverableExceptionInterface;
use App\Model\MachineActionInterface;
use App\Model\ProviderInterface;
use PHPUnit\Framework\TestCase;

class UnknownRemoteMachineExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new UnknownRemoteMachineException(
            ProviderInterface::NAME_DIGITALOCEAN,
            '',
            MachineActionInterface::ACTION_GET,
            new \Exception()
        );

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
