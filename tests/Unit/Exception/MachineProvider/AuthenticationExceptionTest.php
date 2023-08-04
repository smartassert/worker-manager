<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\UnrecoverableExceptionInterface;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new AuthenticationException(md5((string) rand()), MachineAction::GET, new \Exception());

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
