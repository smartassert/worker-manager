<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider\DigitalOcean;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\UnrecoverableExceptionInterface;
use PHPUnit\Framework\TestCase;

class ApiLimitExceededExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new ApiLimitExceededException(0, md5((string) rand()), MachineAction::GET, new \Exception());

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
