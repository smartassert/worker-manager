<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider;

use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\UnrecoverableExceptionInterface;
use App\Model\MachineActionInterface;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new AuthenticationException('', MachineActionInterface::ACTION_GET, new \Exception());

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
