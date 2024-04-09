<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Enum\MachineProvider;
use App\Exception\UnrecoverableExceptionInterface;
use App\Exception\UnsupportedProviderException;
use PHPUnit\Framework\TestCase;

class UnsupportedProviderExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new UnsupportedProviderException(MachineProvider::DIGITALOCEAN);

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
